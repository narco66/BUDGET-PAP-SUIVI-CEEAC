<?php



namespace App\Http\Requests;



use App\Models\Activite;

use App\Models\BudgetRubrique;

use App\Models\LigneBudgetaire;

use App\Services\Budget\ExerciceService;

use App\Services\Budget\StructureBudgetCoherenceService;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;

use Illuminate\Validation\Validator;

use RuntimeException;



class StructureBudgetLigneRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    public function rules(): array

    {

        $ligne = $this->route('ligne');

        $id = $ligne instanceof LigneBudgetaire ? $ligne->id : null;



        return [

            'exercice_id' => ['required', Rule::exists('exercices', 'id')],

            'structure_id' => ['required', Rule::exists('structures', 'id')],

            'rubrique_id' => ['required', Rule::exists('budget_rubriques', 'id')],

            'source_financement_id' => ['nullable', Rule::exists('sources_financement', 'id')],

            'activite_id' => ['nullable', Rule::exists('activites', 'id')],

            'code' => [

                'required', 'string', 'max:50',

                Rule::unique('lignes_budgetaires', 'code')

                    ->where('exercice_id', $this->integer('exercice_id'))

                    ->ignore($id),

            ],

            'intitule' => ['nullable', 'string', 'max:255'],

            'montant_ouvert' => ['required', 'numeric', 'min:0'],

        ];

    }



    public function withValidator(Validator $validator): void

    {

        $validator->after(function (Validator $v) {

            $exerciceId = (int) $this->input('exercice_id');



            try {

                app(ExerciceService::class)->assertBudgetModifiable($exerciceId);

            } catch (RuntimeException $e) {

                $v->errors()->add('exercice_id', $e->getMessage());

            }



            $rubriqueId = (int) $this->input('rubrique_id');

            if ($rubriqueId && ! app(StructureBudgetCoherenceService::class)->rubriqueEstValide($rubriqueId)) {

                $libelle = BudgetRubrique::find($rubriqueId)?->code ?? $rubriqueId;

                $v->errors()->add('rubrique_id', "La rubrique « {$libelle} » n'est pas validée — validez-la dans la nomenclature avant utilisation.");

            }



            $structureId = (int) $this->input('structure_id');

            $activiteId = $this->input('activite_id');



            if ($activiteId && ! Activite::where('id', $activiteId)->where('structure_id', $structureId)->exists()) {

                $v->errors()->add('activite_id', "L'activité choisie n'appartient pas à la structure sélectionnée.");

            }



            $ligne = $this->route('ligne');

            if ($ligne instanceof LigneBudgetaire) {

                $alloue = (float) $this->input('montant_ouvert', $ligne->montant_ouvert);

                if ($alloue + 0.01 < (float) $ligne->montant_engage) {

                    $v->errors()->add('montant_ouvert', 'Le crédit alloué ne peut être inférieur au montant déjà engagé.');

                }

            }

        });

    }



    protected function prepareForValidation(): void

    {

        if ($this->filled('montant_ouvert')) {

            $this->merge([

                'montant_ouvert' => (float) str_replace([' ', ' ', ','], ['', '', '.'], (string) $this->input('montant_ouvert')),

            ]);

        }

    }

}


