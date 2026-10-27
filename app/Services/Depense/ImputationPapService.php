<?php

namespace App\Services\Depense;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\LigneBudgetaireDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imputation budgétaire d'une expression de besoin, telle que présentée dans le budget importé :
 * - volet investissement (PAP) : l'activité est imputée sur l'enveloppe de son axe (ligne « ACT-{code axe} »),
 *   sauf rattachement explicite de l'activité à une ligne ; disponible = reste de l'activité, plafonné par l'enveloppe ;
 * - volet fonctionnement : lignes du budget par code (sans le préfixe technique « LB-{année}- ») et libellé.
 */
class ImputationPapService
{
    /** @var array<string, ?LigneBudgetaire> */
    private array $enveloppes = [];

    /** @var array<int, ?LigneBudgetaire> lignes de rattachement explicite déjà chargées */
    private array $lignes = [];

    /** @var array<int, float>|null engagé par activité (chargé en une requête) */
    private ?array $engages = null;

    /** Ligne d'imputation d'une activité PAP pour un exercice (rattachement explicite, sinon enveloppe de l'axe). */
    public function ligneDe(Activite $activite, ?int $exerciceId = null): ?LigneBudgetaire
    {
        $exerciceId ??= Exercice::query()->where('statut', 'ouvert')->value('id');
        if ($activite->ligne_budgetaire_id) {
            $ligne = $this->lignes[$activite->ligne_budgetaire_id] ??= LigneBudgetaire::find($activite->ligne_budgetaire_id);
            if ($ligne && (! $exerciceId || (int) $ligne->exercice_id === (int) $exerciceId)) {
                return $ligne;
            }
        }

        $activite->loadMissing('produit.axe', 'sousProduit.produit.axe');
        $axe = ($activite->produit ?? $activite->sousProduit?->produit)?->axe;
        if (! $axe || ! $exerciceId) {
            return null;
        }
        $cle = $exerciceId.'|'.$axe->code;

        // Enveloppes « ACT-* » de l'exercice chargées en une requête ; une enveloppe absente reste absente (null).
        if (! array_key_exists($exerciceId.'|*', $this->enveloppes)) {
            $this->enveloppes[$exerciceId.'|*'] = null;
            LigneBudgetaire::query()->where('exercice_id', $exerciceId)->where('code', 'like', 'ACT-%')->where('actif', true)
                ->orderBy('id')->get()->each(function (LigneBudgetaire $l) use ($exerciceId) {
                    $this->enveloppes[$exerciceId.'|'.substr((string) $l->code, 4)] ??= $l;
                });
        }
        if (! array_key_exists($cle, $this->enveloppes)) {
            $this->enveloppes[$cle] = LigneBudgetaire::query()
                ->where('exercice_id', $exerciceId)->where('code', 'ACT-'.$axe->code)->where('actif', true)->first();
        }

        return $this->enveloppes[$cle];
    }

    /** Montant engagé sur l'activité (engagements ayant réservé les crédits). */
    public function engageActivite(Activite $activite): float
    {
        $this->engages ??= DB::table('engagements')->whereNotNull('activite_id')
            ->where('credits_reserves', true)->whereNull('deleted_at')
            ->groupBy('activite_id')->select('activite_id', DB::raw('sum(montant_total) as total'))->pluck('total', 'activite_id')
            ->map(fn ($v) => (float) $v)->all();

        return $this->engages[$activite->id] ?? 0.0;
    }

    /** Disponible de l'activité : crédit PAP de l'activité − engagé, plafonné par le disponible de l'enveloppe. */
    public function disponibleActivite(Activite $activite, ?LigneBudgetaire $ligne): float
    {
        $enveloppe = $ligne ? (float) $ligne->disponible : 0.0;
        if ((float) $activite->montant_total <= 0) {
            return $enveloppe;
        }

        return max(0.0, min($enveloppe, (float) $activite->montant_total - $this->engageActivite($activite)));
    }

    /** Code de la ligne tel qu'il figure dans le budget importé (préfixe technique « LB-AAAA- » retiré). */
    public static function codeBudget(LigneBudgetaire $ligne): string
    {
        return (string) preg_replace('/^LB-\d{4}-/', '', (string) $ligne->code);
    }

    public static function estEnveloppePap(LigneBudgetaire $ligne): bool
    {
        return str_starts_with((string) $ligne->code, 'ACT-');
    }

    /**
     * Options du champ « Ligne budgétaire d'imputation ».
     *
     * @param  Collection<int, Activite>  $activites
     * @param  Collection<int, LigneBudgetaire>  $lignes  lignes du périmètre de l'utilisateur
     * @return array{investissement: list<array<string, mixed>>, fonctionnement: list<array<string, mixed>>}
     */
    public function options(Collection $activites, Collection $lignes, ?int $activiteConservee = null): array
    {
        // Volet investissement : les lignes à six chiffres du PAP (une par activité), sous leur code du document.
        // L'activité déjà rattachée à un besoin reste proposée pour ne pas perdre la saisie existante.
        $exerciceId = Exercice::query()->where('statut', 'ouvert')->value('id');
        // Lignes de rattachement explicite chargées en une requête (au lieu d'une par activité).
        $ids = $activites->pluck('ligne_budgetaire_id')->filter()->unique()->reject(fn ($id) => array_key_exists($id, $this->lignes));
        if ($ids->isNotEmpty()) {
            $this->lignes += LigneBudgetaire::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
        }
        $taches = DB::table('taches')->whereNull('deleted_at')->whereIn('activite_id', $activites->pluck('id'))
            ->groupBy('activite_id')->select('activite_id', DB::raw('count(*) as nb'), DB::raw('sum(montant) as total'))
            ->get()->keyBy('activite_id');
        $pap = $activites->filter(fn (Activite $a) => self::estLignePap($a) || $a->id === $activiteConservee)->map(function (Activite $a) use ($exerciceId, $taches) {
            $ligne = $this->ligneDe($a, $exerciceId);

            return [
                'activite_id' => $a->id, 'ligne_id' => $ligne?->id, 'exercice_id' => $ligne?->exercice_id,
                'total' => (float) $a->montant_total,
                'chaine' => self::chaineRbm($a),
                'responsable_id' => $a->responsable_user_id, 'responsable' => $a->responsable?->name,
                'nb_taches' => (int) ($taches[$a->id]->nb ?? 0), 'total_taches' => (float) ($taches[$a->id]->total ?? 0),
                'code' => self::codePap($a), 'libelle' => $a->intitule,
                'disponible' => $ligne ? $this->disponibleActivite($a, $ligne) : null,
                'enveloppe' => $ligne ? $ligne->code.' — '.$ligne->intitule : null,
            ];
        })->sortBy('code', SORT_NATURAL)->values();

        // Lignes du budget hors enveloppes PAP : fonctionnement (titre 6) et autres dépenses (équipements, assises, dotations).
        $budget = $lignes->reject(fn ($l) => self::estEnveloppePap($l))
            ->sortBy(fn ($l) => self::codeBudget($l), SORT_NATURAL)
            ->map(fn ($l) => [
                'ligne_id' => $l->id, 'exercice_id' => (int) $l->exercice_id, 'activite_id' => $l->activite_id,
                'code' => self::codeBudget($l), 'libelle' => $l->intitule, 'disponible' => (float) $l->disponible,
            ])->values();

        return [
            'pap' => $pap->all(),
            'fonctionnement' => $this->detailsFonctionnement($lignes),
            'equipement' => $budget->reject(fn ($o) => str_starts_with($o['code'], '6'))->values()->all(),
        ];
    }

    /**
     * Volet fonctionnement : lignes à 5 chiffres du document budgétaire (détail des lignes du périmètre).
     * Disponible = montant du détail − besoins déjà exprimés dessus (hors rejetés/annulés), plafonné par la ligne parente.
     *
     * @return list<array<string, mixed>>
     */
    public function detailsFonctionnement(Collection $lignes): array
    {
        $parents = $lignes->keyBy('id');
        $demandes = DB::table('expressions_besoin')->whereNotNull('ligne_detail_id')->whereNull('deleted_at')
            ->whereNotIn('statut', ['rejete', 'annule'])
            ->groupBy('ligne_detail_id')->select('ligne_detail_id', DB::raw('sum(montant_estime) as total'))
            ->pluck('total', 'ligne_detail_id');

        return LigneBudgetaireDetail::query()->whereIn('ligne_budgetaire_id', $parents->keys())->orderBy('code')->get()
            ->map(fn (LigneBudgetaireDetail $d) => [
                'detail_id' => $d->id, 'ligne_id' => $d->ligne_budgetaire_id, 'exercice_id' => $d->exercice_id, 'activite_id' => null,
                'code' => $d->code, 'libelle' => $d->intitule,
                'disponible' => max(0.0, min((float) $parents[$d->ligne_budgetaire_id]->disponible, (float) $d->montant_prevu - (float) ($demandes[$d->id] ?? 0))),
            ])->all();
    }

    /**
     * Ligne d'activité du PAP : code à six chiffres. Est exclu le sous-produit qui ne fait que recopier
     * un article à cinq chiffres sans sous-ligne (code = article + « 0 », même libellé), ex. 201110 pour 20111.
     */
    public static function estLignePap(Activite $activite): bool
    {
        $sp = $activite->sousProduit;
        if (! $sp || ! preg_match('/^\d{6}$/', (string) $sp->code) || ! str_starts_with((string) $activite->code, $sp->code)) {
            return false;
        }
        $produit = $sp->produit;
        $normaliser = fn (?string $t) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $t)));

        return ! ($produit && $sp->code === $produit->code.'0' && $normaliser($sp->intitule) === $normaliser($produit->intitule));
    }

    /** Chaîne RBM de l'activité : Pilier › Axe › Produit › Sous-produit (codes et intitulés). */
    public static function chaineRbm(Activite $activite): string
    {
        $sp = $activite->sousProduit;
        $produit = $activite->produit ?? $sp?->produit;
        $axe = $produit?->axe;
        $pilier = $axe?->pilier;
        $maillon = fn (string $niveau, $n) => $n ? $niveau.' '.$n->code.' — '.$n->intitule : null;

        return implode(' › ', array_filter([
            $maillon('Pilier', $pilier), $maillon('Axe', $axe), $maillon('Produit', $produit),
            $sp && $produit && $sp->code !== $produit->code.'0' ? $maillon('Sous-produit', $sp) : null,
        ]));
    }

    /** Code de l'activité tel qu'il figure dans le Plan annuel de performance (celui de son sous-produit). */
    public static function codePap(Activite $activite): string
    {
        $sp = $activite->sousProduit?->code;

        return $sp && str_starts_with((string) $activite->code, $sp) ? $sp : (string) $activite->code;
    }
}
