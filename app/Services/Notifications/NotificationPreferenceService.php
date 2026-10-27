<?php



namespace App\Services\Notifications;



use App\Models\NotificationChannel;

use App\Models\NotificationType;

use App\Models\User;

use App\Support\NotificationCriticite;



/** Préférences utilisateur et canaux actifs par type (N-P3). */

class NotificationPreferenceService

{

    /** @return list<string> */

    public function canauxEffectifs(User $user, string $typeCode, string $criticite, array $canauxDemandes = ['database'], ?string $nature = null): array

    {

        $prefs = $user->preferences_notifications ?? [];

        // Notification institutionnelle obligatoire (§ 19) : le canal in-app ne peut pas être désactivé.
        $obligatoire = $this->estObligatoire($criticite, $nature);

        if (($prefs['actif'] ?? true) === false) {

            return $obligatoire ? ['database'] : [];

        }



        $canauxType = $this->canauxType($typeCode);

        $canauxUser = $this->canauxUtilisateur($user, $criticite);



        $effectifs = array_values(array_unique(array_filter(

            $canauxDemandes,

            fn (string $c) => in_array($c, $canauxType, true) && in_array($c, $canauxUser, true),

        )));



        if ($obligatoire && ! in_array('database', $effectifs, true)) {
            $effectifs[] = 'database';
        }

        return $effectifs ?: (in_array('database', $canauxType, true) ? ['database'] : []);

    }

    public function estObligatoire(string $criticite, ?string $nature = null): bool
    {
        return in_array($criticite, (array) config('notifications.obligatoires.criticites', ['critique', 'urgence']), true)
            || ($nature !== null && in_array($nature, (array) config('notifications.obligatoires.natures', ['action']), true));
    }



    /** @param  array<string, mixed>  $data */

    public function enregistrer(User $user, array $data): User

    {

        $user->update([

            'preferences_notifications' => [

                'actif' => (bool) ($data['actif'] ?? true),

                'canaux' => [

                    'database' => (bool) ($data['database'] ?? true),

                    'email' => (bool) ($data['email'] ?? true),

                    'sms' => (bool) ($data['sms'] ?? false),

                ],

                'sms_critique_uniquement' => (bool) ($data['sms_critique_uniquement'] ?? true),

            ],

        ]);



        return $user->fresh();

    }



    /** @return list<string> */

    private function canauxType(string $typeCode): array

    {

        $type = NotificationType::where('code', $typeCode)->where('actif', true)->first();

        if (! $type) {

            return ['database'];

        }



        $canaux = NotificationChannel::where('notification_type_id', $type->id)

            ->where('actif', true)

            ->orderBy('ordre')

            ->pluck('canal')

            ->all();



        return $canaux ?: [$type->canal_defaut ?? 'database'];

    }



    /** @return list<string> */

    private function canauxUtilisateur(User $user, string $criticite): array

    {

        $prefs = $user->preferences_notifications ?? [];

        $canaux = [];



        if ($prefs['canaux']['database'] ?? true) {

            $canaux[] = 'database';

        }

        if ($prefs['canaux']['email'] ?? true) {

            $canaux[] = 'email';

        }



        $smsOk = $prefs['canaux']['sms'] ?? false;

        $critiqueOnly = $prefs['sms_critique_uniquement'] ?? true;

        if ($smsOk && (! $critiqueOnly || in_array($criticite, [NotificationCriticite::CRITIQUE, NotificationCriticite::URGENCE], true))) {

            $canaux[] = 'sms';

        }



        return $canaux;

    }

}

