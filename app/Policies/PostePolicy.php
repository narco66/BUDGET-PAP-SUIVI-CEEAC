<?php

namespace App\Policies;

use App\Models\Poste;
use App\Models\User;
use App\Support\ReferentielAdministratifAuthorization;

class PostePolicy
{
    public function viewAny(User $user): bool { return ReferentielAdministratifAuthorization::peut($user, 'voir'); }
    public function view(User $user, Poste $poste): bool { return ReferentielAdministratifAuthorization::peut($user, 'voir'); }
    public function create(User $user): bool { return ReferentielAdministratifAuthorization::peut($user, 'creer'); }
    public function update(User $user, Poste $poste): bool { return ReferentielAdministratifAuthorization::peut($user, 'modifier'); }
    public function delete(User $user, Poste $poste): bool { return ReferentielAdministratifAuthorization::peut($user, 'supprimer'); }
}
