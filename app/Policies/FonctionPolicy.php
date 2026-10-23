<?php

namespace App\Policies;

use App\Models\Fonction;
use App\Models\User;
use App\Support\ReferentielAdministratifAuthorization;

class FonctionPolicy
{
    public function viewAny(User $user): bool { return ReferentielAdministratifAuthorization::peut($user, 'voir'); }
    public function view(User $user, Fonction $f): bool { return ReferentielAdministratifAuthorization::peut($user, 'voir'); }
    public function create(User $user): bool { return ReferentielAdministratifAuthorization::peut($user, 'creer'); }
    public function update(User $user, Fonction $f): bool { return ReferentielAdministratifAuthorization::peut($user, 'modifier'); }
    public function delete(User $user, Fonction $f): bool { return ReferentielAdministratifAuthorization::peut($user, 'supprimer'); }
}
