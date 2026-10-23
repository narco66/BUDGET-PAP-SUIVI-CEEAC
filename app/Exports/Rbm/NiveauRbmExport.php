<?php

namespace App\Exports\Rbm;

use App\Models\Exercice;
use App\Rbm\Niveaux;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NiveauRbmExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly Exercice $exercice,
        private readonly string $niveau,
    ) {
    }

    public function collection(): Collection
    {
        if ($this->niveau === 'pilier') {
            return $this->exercice->piliers()->orderBy('ordre')->get(['code', 'intitule', 'description', 'ordre', 'statut']);
        }

        $config = Niveaux::get($this->niveau);
        $parentNiveau = $config['parent_niveau'];
        $parentConfig = $parentNiveau ? Niveaux::get($parentNiveau) : null;

        if (! $parentConfig) {
            return collect();
        }

        $parentIds = $parentConfig['model']::query()
            ->when($this->niveau !== 'axe', fn ($q) => $q)
            ->when($this->niveau === 'axe', fn ($q) => $q->whereIn('pilier_id', $this->exercice->piliers()->pluck('id')))
            ->when($this->niveau === 'produit', fn ($q) => $q->whereIn('pilier_id', $this->exercice->piliers()->pluck('id')))
            ->pluck('id');

        // Export simplifié : tous les éléments du niveau rattachés à l'exercice via la chaîne
        return $this->exercice->piliers()
            ->with($this->relationPourNiveau())
            ->get()
            ->flatMap(fn ($p) => $this->extraireNiveau($p))
            ->values();
    }

    public function headings(): array
    {
        return ['Code', 'Intitulé', 'Description', 'Ordre', 'Statut'];
    }

    private function relationPourNiveau(): string
    {
        return match ($this->niveau) {
            'axe' => 'axes',
            'produit' => 'axes.produits',
            'sous_produit' => 'axes.produits.sousProduits',
            'activite' => 'axes.produits.sousProduits.activites',
            'tache' => 'axes.produits.sousProduits.activites.taches',
            default => 'axes',
        };
    }

    private function extraireNiveau($pilier): Collection
    {
        $items = collect();
        foreach ($pilier->axes ?? [] as $axe) {
            if ($this->niveau === 'axe') {
                $items->push($axe->only(['code', 'intitule', 'objectif_strategique', 'ordre', 'statut']));
            }
            foreach ($axe->produits ?? [] as $produit) {
                if ($this->niveau === 'produit') {
                    $items->push($produit->only(['code', 'intitule', 'description', 'ordre', 'statut']));
                }
                foreach ($produit->sousProduits ?? [] as $sp) {
                    if ($this->niveau === 'sous_produit') {
                        $items->push($sp->only(['code', 'intitule', 'description', 'ordre', 'statut']));
                    }
                    foreach ($sp->activites ?? [] as $act) {
                        if ($this->niveau === 'activite') {
                            $items->push($act->only(['code', 'intitule', 'description', 'ordre', 'statut']));
                        }
                        foreach ($act->taches ?? [] as $tache) {
                            if ($this->niveau === 'tache') {
                                $items->push([
                                    'code' => $tache->code,
                                    'intitule' => $tache->intitule,
                                    'description' => $tache->statut,
                                    'ordre' => $tache->ordre,
                                    'statut' => $tache->statut_referentiel ?? 'actif',
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return $items;
    }
}
