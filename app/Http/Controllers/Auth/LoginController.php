<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Services\AdministrationTechnique\ProtectionConnexionService;
use App\Services\Auth\MfaService;
use App\Support\AuditLogger;
use App\Support\JournalSecurite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, ProtectionConnexionService $protection, MfaService $mfa): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Anti brute-force : IP verrouillée après trop d'échecs.
        if ($bloc = $protection->ipBloquee($request->ip())) {
            $this->journaliser($request, null, false, 'IP verrouillée');

            return back()->withErrors(['email' => 'Trop de tentatives échouées. Réessayez '.$bloc->bloque_jusqua?->diffForHumans().'.'])->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->journaliser($request, null, false, 'Identifiants invalides');
            $protection->enregistrerEchec($request->ip());

            return back()->withErrors(['email' => 'Identifiants invalides.'])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->estActif()) {
            $this->journaliser($request, $user, false, 'Compte '.$user->statut);
            Auth::logout();

            return back()->withErrors(['email' => 'Ce compte est '.$user->statut.'.']);
        }

        if ($mfa->estActive() && $mfa->aMfaActive($user)) {
            $remember = $request->boolean('remember');
            Auth::logout();
            $request->session()->put('mfa.id', $user->id);
            $request->session()->put('mfa.remember', $remember);

            return redirect()->route('mfa.challenge');
        }

        $request->session()->regenerate();
        $protection->reinitialiser($request->ip());
        $user->forceFill(['derniere_connexion_at' => now()])->save();
        $this->journaliser($request, $user, true);
        AuditLogger::enregistrer('login', $user);

        if ($user->doit_changer_mot_de_passe) {
            return redirect()->route('password.edit');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLogger::enregistrer('logout', Auth::user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function journaliser(Request $request, ?object $user, bool $succes, ?string $motif = null): void
    {
        LoginLog::create([
            'user_id' => $user?->id,
            'email' => $request->input('email'),
            'succes' => $succes,
            'motif' => $motif,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        if (! $succes) {
            JournalSecurite::echecConnexion((string) $request->input('email'), (string) $motif);
        }
    }
}
