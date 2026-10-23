<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Modèle d'import de la nomenclature (en-têtes + ligne d'exemple). */
class ModeleNomenclatureExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'sens', 'titre_code', 'titre_intitule', 'article_code', 'article_intitule',
            'paragraphe_code', 'paragraphe_intitule', 'rubrique_code', 'rubrique_intitule',
        ];
    }

    public function array(): array
    {
        return [
            ['depense', '1', 'Dépenses de personnel', '66', 'Charges de personnel', '661', 'Traitements et salaires', '661000', 'Traitements et salaires'],
            ['depense', '2', 'Dépenses de fonctionnement', '60', 'Achats de biens', '601', 'Matières, matériel et fournitures', '601100', 'Fournitures de bureau'],
            ['recette', '1', 'Recettes ordinaires globalisées', '72', 'Contributions statutaires', '721', 'Contributions États membres CCI', '721010', "République d'Angola"],
        ];
    }
}
