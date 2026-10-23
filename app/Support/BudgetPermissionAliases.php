<?php



namespace App\Support;



use App\Models\User;



/** Alias permissions budget anglais → nomenclature FR (phases B1–B5). */

final class BudgetPermissionAliases

{

    /** @var array<string, string> */

    public const MAP = [

        'budget.view' => 'budget.voir',

        'budget.create' => 'budget.creer',

        'budget.update' => 'budget.modifier',

        'budget.delete' => 'budget.supprimer',

        'budget.validate' => 'budget.valider',

        'budget.arbitrate' => 'budget.arbitrer',

        'budget.consolidate' => 'budget.consolider',

        'budget.simulate' => 'budget.simuler',

        'budget.amend' => 'budget.rectifier',

        'budget.prepare' => 'budget.modifier',

        'budget.import' => 'budget.importer',

        'budget.audit' => 'budget.voir',

        'budget.report' => 'budget.exporter',

        'budget.revise' => 'budget.modifier',

        'budget.export' => 'budget.exporter',

        'budget.admin' => 'budget.modifier',

    ];



    public static function resolve(string $ability): ?string

    {

        return self::MAP[$ability] ?? null;

    }



    public static function peut(User $user, string $abilityAnglais): bool

    {

        $fr = self::resolve($abilityAnglais);



        return $fr !== null && $user->can($fr);

    }

}

