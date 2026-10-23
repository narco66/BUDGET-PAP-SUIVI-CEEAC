<?php



namespace App\Http\Controllers;



use App\Services\Notifications\AuditNotificationService;

use App\Services\Notifications\NotificationPreferenceService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;



/** Préférences de notifications utilisateur (N-P3). */

class ProfilNotificationController extends Controller

{

    public function edit(Request $request): View

    {

        abort_unless($request->user()->can('notifications.manage'), 403);



        $prefs = $request->user()->preferences_notifications ?? [];



        return view('profil.notifications', [

            'prefs' => $prefs,

        ]);

    }



    public function update(Request $request, NotificationPreferenceService $preferences, AuditNotificationService $audit): RedirectResponse

    {

        abort_unless($request->user()->can('notifications.manage'), 403);



        $data = $request->validate([

            'actif' => 'nullable|boolean',

            'database' => 'nullable|boolean',

            'email' => 'nullable|boolean',

            'sms' => 'nullable|boolean',

            'sms_critique_uniquement' => 'nullable|boolean',

        ]);



        $user = $preferences->enregistrer($request->user(), [

            'actif' => $request->boolean('actif', true),

            'database' => $request->boolean('database', true),

            'email' => $request->boolean('email', true),

            'sms' => $request->boolean('sms'),

            'sms_critique_uniquement' => $request->boolean('sms_critique_uniquement', true),

        ]);



        $audit->journaliser(

            'preference_maj',

            utilisateur: $user,

            payload: $user->preferences_notifications ?? [],

        );



        return back()->with('succes', 'Préférences de notifications enregistrées.');

    }

}

