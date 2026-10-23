<?php

namespace Database\Seeders;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use Illuminate\Database\Seeder;

/**
 * Nomenclature budgétaire CEEAC — reprise COMPLÈTE.
 * Annexe 1-a : recettes + dépenses par nature économique.
 * Annexe 1-b : financement du solde (ressources / besoins).
 * Annexe 1-c : engagements hors-bilan (obtenus / accordés).
 * Format des données : [titreCode, titreLib, [ [artCode, artLib, [ [paraCode, paraLib, [ [rubCode, rubLib], ... ]], ... ]], ... ]].
 */
class NomenclatureBudgetaireSeeder extends Seeder
{
    public function run(): void
    {
        $this->persist('recette', $this->recettes());
        $this->persist('depense', $this->depenses());
        $this->persist('financement', $this->financement());
        $this->persist('hors_bilan', $this->horsBilan());
    }

    private function recettes(): array
    {
        $don = fn () => [
            ['741', 'Dons projets/programmes', [
                ['741100', 'Don projets/programmes États membres'], ['741200', 'Don projets/programmes Multilatéral'],
                ['741300', 'Don projets/programmes Bilatéral'], ['741400', 'Don projets/programmes des organismes Africains'],
                ['741500', 'Dons des organismes privés'],
            ]],
            ['742', "Subventions d'exploitation", [
                ['742100', 'Subventions États membres'], ['742200', 'Subventions PTF Multilatéral'],
                ['742300', 'Subventions PTF Bilatéral'], ['742400', 'Subventions des organismes Africains'],
                ['742500', 'Subventions autres organismes'],
            ]],
            ['743', 'Fonds de concours', [
                ['743100', 'Fonds de concours États membres'], ['743200', 'Fonds de concours PTF Multilatéral'],
                ['743300', 'Fonds de concours PTF Bilatéral'], ['743400', 'Fonds de concours des organismes Africains'],
                ['743500', 'Fonds de concours autres organismes'],
            ]],
            ['749', 'Autres dons et legs', [['749000', 'Autres dons et legs']]],
        ];

        $subvInvest = ['141', "Subventions d'investissement", [
            ['141100', 'Subventions des États membres'], ['141200', 'Subventions des Partenaires Techniques et Financiers'],
            ['141300', 'Subventions autres organismes et institutions'], ['141800', "Autres subventions d'investissement"],
        ]];

        return [
            ['1', 'Recettes ordinaires globalisées', [
                ['70', 'Ventes de produits et services', [
                    ['701', 'Ventes de produits', [['701000', 'Ventes de produits']]],
                    ['702', 'Ventes de prestations de services', [['702000', 'Ventes de prestations de services']]],
                    ['703', 'Variation de stocks de produits et services', [['703000', 'Variation de stocks de produits et services']]],
                ]],
                ['72', 'Contributions statutaires & appuis budgétaires', [
                    ['721', 'Contributions États membres CCI', [
                        ['721010', "République d'Angola"], ['721020', 'République du Burundi'], ['721030', 'République du Cameroun'],
                        ['721040', 'République Centrafricaine'], ['721050', 'République Populaire du Congo'], ['721060', 'République Démocratique du Congo'],
                        ['721070', 'République Gabonaise'], ['721080', 'République de Guinée Équatoriale'], ['721090', 'République Rwandaise'],
                        ['721100', 'République Démocratique de Sao Tomé-et-Principe'], ['721110', 'République du Tchad'],
                    ]],
                    ['722', 'Droits et frais administratifs', [['722000', 'Droits et frais administratifs']]],
                    ['723', 'Amendes, pénalités et condamnations pécuniaires', [['723000', 'Amendes, pénalités et condamnations pécuniaires']]],
                    ['725', 'Cotisations de sécurité sociale', [['725000', 'Cotisations de sécurité sociale']]],
                    ['729', 'Appuis budgétaires et autres recettes', [
                        ['729100', 'Appui budgétaire Multilatéral'], ['729200', 'Appui budgétaire Bilatéral et Gouvernements Étrangers'],
                        ['729400', 'Appui budgétaire CER'], ['729500', 'Autres recettes et contributions diverses'],
                    ]],
                ]],
                ['77', 'Produits financiers', [
                    ['771', 'Intérêts des prêts', [['771000', 'Intérêts des prêts']]],
                    ['772', 'Intérêts sur les dépôts à terme', [['772000', 'Intérêts sur les dépôts à terme']]],
                    ['774', 'Intérêts sur titres de placement', [['774000', 'Intérêts sur titres de placement']]],
                    ['776', 'Gains de change', [['776000', 'Gains de change']]],
                ]],
            ]],
            ['2', 'Recettes extraordinaires globalisées', [
                ['73', "Transferts reçus d'autres budgets de la Communauté", [
                    ['731', 'Transferts reçus du budget général', [['731000', 'Transferts reçus du budget général']]],
                    ['732', 'Transferts reçus des budgets annexes et comptes spéciaux', [
                        ['732100', 'Transferts reçus des budgets annexes'], ['732200', 'Transferts reçus des comptes spéciaux'],
                    ]],
                ]],
                ['74', 'Dons projets/programmes, subventions, legs et fonds de concours', $don()],
                ['75', 'Produits exceptionnels', [
                    ['751', 'Remises et annulations de dettes', [['751000', 'Remises et annulations de dettes']]],
                    ['752', "Restitutions à l'agence comptable de sommes indûment payées", [['752000', "Restitutions à l'agence comptable des sommes indûment payées"]]],
                    ['753', 'Contributions spéciales des États membres et subventions diverses', [['753000', 'Contributions spéciales des États membres et subventions diverses']]],
                    ['754', "Produits de cessions d'immobilisations", [['754000', "Produits de cession d'immobilisations"]]],
                    ['759', 'Autres recettes exceptionnelles', [['759000', 'Autres recettes exceptionnelles']]],
                ]],
                ['14', "Subventions d'investissement reçues", [$subvInvest]],
            ]],
            ['3', 'Recettes extraordinaires fléchées', [
                ['74', 'Dons projets/programmes, subventions, legs et fonds de concours', $don()],
                ['10', "Ressources permanentes, financement de l'actif", [
                    ['101', 'Biens remis à disposition de la Communauté', [
                        ['101100', 'Dotation initiale des États membres'], ['101200', 'Dotation initiale des PTF'],
                        ['101300', 'Dotation initiale autres organismes'], ['101400', 'Dotation complémentaire des États membres'],
                        ['101500', 'Dotation complémentaire des PTF'], ['101600', 'Dotation complémentaire autres organismes'],
                        ['101800', 'Autres dotations'],
                    ]],
                    ['105', 'Écarts de réévaluation', [
                        ['105100', 'Écarts de réévaluation sur immobilisations incorporelles'],
                        ['105200', 'Écarts de réévaluation sur immobilisations corporelles'],
                    ]],
                ]],
                ['14', "Subventions d'investissement reçues", [$subvInvest]],
            ]],
        ];
    }

    private function depenses(): array
    {
        return [
            ['1', 'Dépenses de personnel', [
                ['66', 'Charges de personnel', [
                    ['661', 'Traitements et salaires', [['661000', 'Traitements et salaires']]],
                    ['663', 'Primes et indemnités', [['663000', 'Primes et indemnités']]],
                    ['664', 'Cotisations sociales', [['664000', 'Cotisations sociales']]],
                    ['665', 'Avantages en nature au personnel', [['665000', 'Avantages en nature au personnel']]],
                    ['666', 'Prestations sociales', [['666000', 'Prestations sociales']]],
                    ['669', 'Autres dépenses de personnel', [['669000', 'Autres dépenses de personnel']]],
                ]],
            ]],
            ['2', 'Dépenses de fonctionnement', [
                ['67', 'Intérêts et frais financiers', [
                    ['671', 'Intérêts et frais financiers sur la dette', [['671000', 'Intérêts et frais financiers sur la dette']]],
                    ['672', 'Pertes sur cessions de titres de placement', [['672000', 'Pertes sur cessions de titres de placement']]],
                    ['676', 'Pertes de change', [['676000', 'Pertes de change']]],
                    ['679', 'Autres intérêts et frais financiers', [['679000', 'Autres intérêts et frais financiers']]],
                ]],
                ['60', 'Achats de biens', [
                    ['601', 'Matières, matériel et fournitures', [
                        ['601100', 'Fournitures de bureau'], ['601200', 'Fournitures et consommables informatiques'],
                        ['601300', 'Carburant et lubrifiant'], ['601400', "Produits, outillages et petits matériels d'entretien"],
                    ]],
                    ['603', 'Variations des stocks de biens fongibles achetés', [['603000', 'Variations des stocks de biens fongibles achetés']]],
                    ['605', "Eau, électricité, gaz et autres sources d'énergie", [['605000', "Eau, électricité, gaz et autres sources d'énergie"]]],
                    ['606', 'Matériel et fournitures spécifiques', [['606000', 'Matériel et fournitures spécifiques']]],
                    ['609', 'Autres achats de biens', [['609000', 'Autres achats de biens']]],
                ]],
                ['61', 'Achats de services', [
                    ['611', 'Frais de transport et de mission', [
                        ['611100', 'Transport du personnel et des plis'], ['611200', 'Voyages et déplacements'], ['611300', 'Frais de missions'],
                    ]],
                    ['612', 'Loyers et charges locatives', [
                        ['612100', 'Location des terrains'], ['612200', 'Location des bâtiments'], ['612300', 'Locations de matériels et outillages'],
                    ]],
                    ['614', 'Entretien et maintenance', [
                        ['614100', 'Entretien et réparations des biens immobiliers'], ['614200', 'Entretien et réparations de matériels et mobiliers'],
                        ['614300', 'Maintenance'], ['614400', 'Service extérieur de gardiennage et de nettoyage'],
                    ]],
                    ['615', 'Assurances', [['615000', 'Assurances']]],
                    ['617', 'Frais de relations publiques', [
                        ['617100', 'Frais de réception et de représentation'], ['617200', 'Annonces, insertions et publications'],
                        ['617300', 'Catalogues, imprimés publicitaires et traductions'], ['617400', 'Frais de séminaires et ateliers'],
                        ['617500', 'Fêtes et cérémonies'], ['617600', 'Documentations générales et techniques'],
                    ]],
                    ['618', 'Dépenses de communications', [
                        ['618100', 'Frais de téléphone et télécopie'], ['618200', 'Internet et site web'], ['618300', 'Frais de poste et télécommunication'],
                    ]],
                ]],
                ['62', 'Autres services', [
                    ['621', 'Frais bancaires', [
                        ['621000', 'Frais bancaires banque centrale'], ['621100', 'Frais bancaires comptes postaux'], ['621200', 'Frais bancaires banques commerciales'],
                    ]],
                    ['622', "Rémunérations d'intermédiaires et de conseils", [
                        ['622100', 'Honoraires, études et prestations de services'], ['622200', "Frais d'actes et de contentieux"],
                    ]],
                    ['623', 'Frais de recrutement et formation du personnel', [
                        ['623100', 'Frais de recrutement du personnel'], ['623200', 'Frais de formation du personnel'], ['623300', 'Frais de déménagement'],
                    ]],
                    ['624', 'Redevances pour brevets, licences et logiciels', [['624000', 'Redevances pour brevets, licences et logiciels']]],
                    ['629', 'Autres acquisitions de services', [['629000', 'Autres acquisitions de services']]],
                ]],
                ['65', 'Charges exceptionnelles', [
                    ['651', 'Annulations de produits constatés au cours des années antérieures', [['651000', 'Annulations de produits constatés au cours des années antérieures']]],
                    ['652', 'Condamnations et transactions', [['652000', 'Condamnations et transactions']]],
                    ['653', 'Jetons de présence', [['653000', 'Jetons de présence aux différentes assises']]],
                    ['654', 'Valeurs comptables des immobilisations cédées', [['654000', 'Valeurs comptables des immobilisations cédées, mises au rebut ou en non-valeur']]],
                    ['659', 'Autres charges exceptionnelles', [['659000', 'Autres charges exceptionnelles']]],
                ]],
            ]],
            ['3', "Dépenses d'intervention", [
                ['63', 'Subventions versées', [
                    ['632', 'Subventions aux entités publiques', [['632000', 'Subventions aux administrations publiques'], ['632100', 'Subventions aux collectivités locales']]],
                    ['633', 'Subventions aux entreprises privées et OSC', [['633100', 'Subventions aux entreprises privées'], ['633200', 'Subventions aux OSC']]],
                    ['634', 'Subventions aux institutions financières', [['634000', 'Subventions aux institutions financières']]],
                    ['639', "Subventions à d'autres catégories de bénéficiaires", [['639000', "Subventions à d'autres catégories de bénéficiaires"]]],
                ]],
                ['64', 'Transferts versés', [
                    ['641', 'Transferts aux institutions de la Communauté', [
                        ['641100', 'Transferts au Parlement de la Communauté'], ['641200', 'Transferts à la Cour de Justice'],
                        ['641300', 'Transferts à la Cour des Comptes'], ['641400', 'Transferts aux Institutions spécialisées'],
                        ['641500', 'Transferts aux Institutions financières'],
                    ]],
                    ['644', 'Transferts aux bureaux de liaison de la CEEAC', [['644000', 'Transferts aux bureaux de liaison de la CEEAC']]],
                    ['645', 'Transferts aux CER et contributions aux organisations internationales', [
                        ['645000', 'Transferts aux CER du Continent Africain'], ['645100', 'Contributions aux organisations internationales'],
                    ]],
                    ['647', 'Transferts aux comptes spéciaux', [['647000', 'Transferts aux comptes spéciaux']]],
                    ['648', 'Pensions de retraites des fonctionnaires et agents', [['648000', 'Pensions de retraites des fonctionnaires et autres agents']]],
                    ['649', 'Autres transferts', [['649000', 'Autres transferts']]],
                ]],
            ]],
            ['4', "Dépenses d'investissements et d'équipements", [
                ['21', 'Immobilisations incorporelles', [
                    ['211', 'Frais de recherche et de développement', [['211000', 'Frais de recherche et développement']]],
                    ['212', "Brevets, marques de fabrique, droits d'auteur", [['212000', "Brevets, marques de fabrique, droits d'auteur"]]],
                    ['213', "Conceptions de systèmes d'organisation - progiciels", [['213000', "Conceptions de systèmes d'organisation - progiciels"]]],
                    ['214', "Droits d'exploitation fonds de commerce", [['214000', "Droits d'exploitation de fonds de commerce"]]],
                    ['219', 'Autres droits et valeurs incorporels', [['219000', 'Autres droits et valeurs incorporels']]],
                ]],
                ['22', 'Acquisitions et aménagements des sols et sous-sols', [
                    ['221', 'Terrains', [['221000', 'Terrains']]],
                    ['222', 'Sous-sols, gisements et carrières', [['222000', 'Sous-sols, gisements et carrières']]],
                    ['223', 'Plantation et forêts', [['223000', 'Plantations et forêts']]],
                    ['224', "Plans d'eau", [['224000', "Plans d'eau"]]],
                ]],
                ['23', 'Acquisitions, constructions et grosses réparations des immeubles', [
                    ['231', 'Bâtiments administratifs à usage de bureau', [['231000', 'Bâtiments administratifs à usage de bureau']]],
                    ['232', 'Bâtiments administratifs à usage de logement', [['232000', 'Bâtiments administratifs à usage de logement']]],
                    ['233', 'Bâtiments administratifs à usage technique', [['233000', 'Bâtiments administratifs à usage technique']]],
                    ['234', 'Ouvrages', [['234000', 'Ouvrages']]],
                    ['235', 'Infrastructures', [['235000', 'Infrastructures']]],
                    ['236', 'Réseaux informatiques', [['236000', 'Réseaux informatiques']]],
                    ['239', 'Bâtiments, aménagements et installations en cours', [
                        ['239100', 'Bâtiments en cours'], ['239200', 'Ouvrages en cours'], ['239300', 'Infrastructures en cours'], ['239400', 'Réseaux informatiques en cours'],
                    ]],
                ]],
                ['24', 'Acquisitions et grosses réparations du matériel et mobilier', [
                    ['241', 'Mobilier et matériel de logement et de bureau', [['241000', 'Mobilier et matériel de logement et de bureau']]],
                    ['242', 'Matériel informatique de bureau', [['242000', 'Matériel informatique de bureau']]],
                    ['243', 'Matériel de transport de service et de fonction', [['243000', 'Matériel de transport de service et de fonction']]],
                    ['244', 'Matériel et outillage techniques', [['244000', 'Matériel et outillage techniques']]],
                    ['245', 'Matériel de transport en commun et de marchandises', [['245000', 'Matériel de transport en commun et de marchandises']]],
                    ['246', "Objets de valeur - collections - œuvres d'art", [['246000', "Objets de valeur - collections - œuvres d'art"]]],
                    ['247', "Stocks stratégiques ou d'urgence", [['247000', "Stocks stratégiques ou d'urgence"]]],
                    ['248', 'Cheptel', [['248000', 'Cheptel']]],
                ]],
                ['25', 'Équipements militaires', [
                    ['251', 'Bases militaires', [['251000', 'Bases militaires']]],
                    ['252', 'Ouvrages et infrastructures militaires', [['252000', 'Ouvrages et infrastructures militaires']]],
                    ['253', 'Mobiliers, matériels militaires et équipements', [['253000', 'Mobiliers, matériels militaires et équipements']]],
                ]],
                ['26', 'Prises de participations et cautionnements', [
                    ['261', "Prises de participation à l'intérieur de la Communauté", [['261000', "Prises de participation à l'intérieur de la communauté"]]],
                    ['262', "Prises de participation à l'extérieur de la Communauté", [['262000', "Prises de participation à l'extérieur de la communauté"]]],
                ]],
            ]],
        ];
    }

    private function financement(): array
    {
        $emprunts = fn (string $prefixe, string $libelle) => [
            ['15', $prefixe.' emprunts projets', [['151', 'Emprunts projets', [
                ['151100', $libelle.' projets des États membres'], ['151200', $libelle.' projets des PTF'], ['151300', $libelle.' projets autres organismes'],
            ]]]],
            ['16', $prefixe.' emprunts programmes', [['161', 'Emprunts programmes', [
                ['161100', $libelle.' programmes des États membres'], ['161200', $libelle.' programmes des PTF'], ['161300', $libelle.' programmes autres organismes'],
            ]]]],
            ['17', $prefixe.' autres emprunts', [['171', 'Autres emprunts', [
                ['171100', $libelle.' auprès des États membres'], ['171200', $libelle.' auprès des PTF'], ['171300', $libelle.' auprès des autres organismes'],
            ]]]],
            ['26', 'Prises de participations et cautionnements', [['264', 'Cautionnements', [['264000', $libelle.' / dépôt de cautionnements']]]]],
            ['27', 'Prêts et avances', [
                ['271', 'Avances et prêts aux personnels', [['271000', $libelle.' avances et prêts aux personnels']]],
                ['272', 'Prêts aux organes et institutions spécialisées', [['272000', $libelle.' prêts aux organes et institutions spécialisées']]],
                ['275', 'Autres prêts intérieurs', [['275000', $libelle.' autres prêts intérieurs']]],
            ]],
        ];

        return [
            ['RES', 'Ressources pour financement du solde budgétaire', $emprunts('', 'Emprunts')],
            ['BES', 'Besoins de financement', $emprunts('Remboursement', 'Remboursement')],
        ];
    }

    private function horsBilan(): array
    {
        return [
            ['OBT', 'Engagements obtenus des partenaires de la CEEAC', [
                ['80', 'Engagements obtenus ou accordés par la Communauté', [['801', 'Engagements obtenus par la Communauté', [
                    ['801100', 'Emprunts projets/programmes obtenus'], ['801200', 'Dons projets/programmes obtenus'], ['801900', 'Autres engagements obtenus'],
                ]]]],
                ['81', 'Contrepartie des engagements de la Communauté', [['815', 'Contrepartie des engagements accordés', [
                    ['815100', 'Contrepartie des prêts accordés et dette avalisée'], ['815200', 'Contrepartie des dons accordés'],
                    ['815300', 'Contrepartie des engagements financiers - cofinancement'], ['815900', 'Contrepartie des autres engagements donnés'],
                ]]]],
            ]],
            ['ACC', 'Engagements pris par la CEEAC vis-à-vis des partenaires', [
                ['80', 'Engagements obtenus ou accordés par la Communauté', [['805', 'Engagements accordés par la Communauté', [
                    ['805100', 'Prêts accordés et dette avalisée'], ['805200', 'Dons accordés par la Communauté'],
                    ['805300', 'Engagements financiers - cofinancement'], ['805900', 'Autres engagements donnés'],
                ]]]],
                ['81', 'Contrepartie des engagements de la Communauté', [['811', 'Contrepartie des engagements obtenus', [
                    ['811100', 'Contrepartie des emprunts projets/programmes obtenus'], ['811200', 'Contrepartie des dons projets/programmes obtenus'],
                    ['811900', 'Contrepartie des autres engagements obtenus'],
                ]]]],
            ]],
        ];
    }

    /** @param array<int,array{0:string,1:string,2:array}> $titres */
    private function persist(string $sens, array $titres): void
    {
        foreach ($titres as $ti => [$tCode, $tLib, $articles]) {
            $titre = BudgetTitre::updateOrCreate(['sens' => $sens, 'code' => $tCode], ['intitule' => $tLib, 'ordre' => $ti]);

            foreach ($articles as $ai => [$aCode, $aLib, $paragraphes]) {
                $article = BudgetArticle::updateOrCreate(['titre_id' => $titre->id, 'code' => $aCode], ['intitule' => $aLib, 'ordre' => $ai]);

                foreach ($paragraphes as $pi => [$pCode, $pLib, $rubriques]) {
                    $paragraphe = BudgetParagraphe::updateOrCreate(['article_id' => $article->id, 'code' => $pCode], ['intitule' => $pLib, 'ordre' => $pi]);

                    foreach ($rubriques as $ri => [$rCode, $rLib]) {
                        BudgetRubrique::updateOrCreate(
                            ['paragraphe_id' => $paragraphe->id, 'code' => $rCode],
                            ['intitule' => $rLib, 'ordre' => $ri]
                        );
                    }
                }
            }
        }
    }
}
