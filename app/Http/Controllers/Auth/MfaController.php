<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Http\Requests\Auth\MfaSetupRequest;
use App\Models\User;
use App\Services\Auth\MfaService;
use App\Services\AdministrationTechnique\ProtectionConnexionService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function __construct(private readonly MfaService $mfa)
    {
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('mfa.id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function verifyChallenge(MfaChallengeRequest $request, ProtectionConnexionService $protection): RedirectResponse
    {
        $userId = $request->session()->get('mfa.id');
        $user = User::find($userId);

        if (! $user || ! $user->estActif()) {
            $request->session()->forget(['mfa.id', 'mfa.remember']);

            return redirect()->route('login')->withErrors(['email' => 'Session expirée. Reconnectez-vous.']);
        }

        $ok = $request->filled('recovery_code')
            ? $this->mfa->verifierCodeSecours($user, $request->input('recovery_code'))
            : $this->mfa->verifierTotp($user, $request->input('code', ''));

        if (! $ok) {
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        $remember = (bool) $request->session()->pull('mfa.remember');
        $request->session()->forget('mfa.id');
        $request->session()->regenerate();

        Auth::login($user, $remember);
        $protection->reinitialiser($request->ip());
        $user->forceFill(['derniere_connexion_at' => now()])->save();
        AuditLogger::enregistrer('login', $user);

        if ($user->doit_changer_mot_de_passe) {
            return redirect()->route('password.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function setup(Request $request): View
    {
        $user = $request->user();

        if ($this->mfa->aMfaActive($user)) {
            return view('auth.mfa-setup', [
                'actif' => true,
                'codesSecours' => null,
                'secret' => null,
                'qr' => null,
            ]);
        }

        $secret = $request->session()->get('mfa.pending_secret', $this->mfa->genererSecret());
        $request->session()->put('mfa.pending_secret', $secret);

        return view('auth.mfa-setup', [
            'actif' => false,
            'secret' => $secret,
            'qr' => $this->mfa->qrDataUri($user, $secret),
            'codesSecours' => null,
        ]);
    }

    public function confirmSetup(MfaSetupRequest $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get('mfa.pending_secret');

        if (! $secret || ! $this->mfa->verifierTotpAvecSecret($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Code incorrect. Rescannez le QR code si besoin.']);
        }

        $codes = $this->mfa->genererCodesSecours();
        $this->mfa->activer($user, $secret, $codes);
        $request->session()->forget('mfa.pending_secret');
        $request->session()->flash('codes_secours', $codes);

        AuditLogger::enregistrer('mfa_active', $user);

        return redirect()->route('mfa.setup')->with('succes', 'Authentification à deux facteurs activée.');
    }

    public function disable(Request $request): RedirectResponse
    {
        if ($this->mfa->estObligatoire($request->user())) {
            return back()->with('erreur', 'La MFA est obligatoire pour votre rôle et ne peut pas être désactivée.');
        }

        $this->mfa->desactiver($request->user());
        $request->session()->forget('mfa.pending_secret');
        AuditLogger::enregistrer('mfa_desactive', $request->user());

        return back()->with('succes', 'Authentification à deux facteurs désactivée.');
    }
}
