<?php

namespace App\Console\Commands;

use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/** Empreintes de volume (sans secrets) pour contrôler les données protégées. */
class ChaineDepenseEmpreintesCommand extends Command
{
    protected $signature = 'chaine-depense:empreintes';

    protected $description = 'Affiche des compteurs déterministes (users, rôles, structures, lignes) sans exposer de secrets.';

    public function handle(): int
    {
        $users = User::query()->count();
        $roles = Role::query()->count();
        $structures = Structure::query()->count();
        $lignes = LigneBudgetaire::query()->count();
        $hashUsers = hash('sha256', User::query()->orderBy('id')->pluck('id')->implode(','));
        $hashRoles = hash('sha256', Role::query()->orderBy('id')->pluck('name')->implode(','));

        $this->table(['Indicateur', 'Valeur'], [
            ['utilisateurs', (string) $users],
            ['roles', (string) $roles],
            ['structures', (string) $structures],
            ['lignes_budgetaires', (string) $lignes],
            ['empreinte_ids_users', $hashUsers],
            ['empreinte_noms_roles', $hashRoles],
        ]);

        return self::SUCCESS;
    }
}
