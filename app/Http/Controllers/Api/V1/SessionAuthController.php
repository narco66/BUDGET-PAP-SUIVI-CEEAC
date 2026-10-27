<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SessionLoginRequest;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Http\Requests\Auth\MfaSetupRequest;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\AdministrationTechnique\ProtectionConnexionService;
use App\Services\Api\ProfilApiService;
use App\Services\Auth\MfaService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionAuthController extends Controller
{
    public function __construct(
        private readonly ProtectionConnexionService $protection,
        private readonly MfaService $mfa,
        private readonly ProfilApiService $profil,
    ) {}

    public function login(SessionLoginRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $password = $request->validated('password');
        $remember = $request->boolean('remember');

        if ($bloc = $this->protection->ipBloquee($request->ip())) {
            $this->journaliser($request, null, false, 'IP verrouillée');

            return response()->json([
                'message' => 'Trop de tentatives échouées. Réessayez '.$bloc->bloque_jusqua?->diffForHumans().'.',
                'code' => 'ip_blocked',
            ], 429);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            $this->journaliser($request, null, false, 'Identifiants invalides');
            $this->protection->enregistrerEchec($request->ip());

            return response()->json([
                'message' => 'Identifiants invalides.',
                'code' => 'invalid_credentials',
            ], 422);
        }

        $user = Auth::user();

        if (! $user->estActif()) {
            $this->journaliser($request, $user, false, 'Compte '.$user->statut);
            Auth::logout();

            return response()->json([
                'message' => 'Ce compte est '.$user->statut.'.',
                'code' => 'account_inactive',
            ], 403);
        }

        if ($this->mfa->estActive() && $this->mfa->aMfaActive($user)) {
            Auth::logout();
            $request->session()->put('mfa.id', $user->id);
            $request->session()->put('mfa.remember', $remember);

            return response()->json([
                'status' => 'mfa_required',
                'message' => 'Saisissez le code d\'authentification à deux facteurs.',
                'code' => 'mfa_required',
            ]);
        }

        return $this->finaliserSession($request, $user, $remember);
    }

    public function mfa(MfaChallengeRequest $request): JsonResponse
    {
        $userId = $request->session()->get('mfa.id');
        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->estActif()) {
            $request->session()->forget(['mfa.id', 'mfa.remember']);

            return response()->json([
                'message' => 'Session expirée. Reconnectez-vous.',
                'code' => 'mfa_expired',
            ], 401);
        }

        $ok = $request->filled('recovery_code')
            ? $this->mfa->verifierCodeSecours($user, $request->input('recovery_code'))
            : $this->mfa->verifierTotp($user, $request->input('code', ''));

        if (! $ok) {
            return response()->json([
                'message' => 'Code invalide ou expiré.',
                'code' => 'mfa_invalid',
                'errors' => ['code' => ['Code invalide ou expiré.']],
            ], 422);
        }

        $remember = (bool) $request->session()->pull('mfa.remember');
        $request->session()->forget('mfa.id');

        return $this->finaliserSession($request, $user, $remember);
    }

    public function mfaSetup(Request $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Une session navigateur est requise.', 'code' => 'session_requise'], 422);
        }

        $user = $request->user();
        if ($this->mfa->aMfaActive($user)) {
            return response()->json([
                'actif' => true,
                'obligatoire' => $this->mfa->estObligatoire($user),
                'secret' => null,
                'qr' => null,
            ]);
        }

        $secret = $request->session()->get('mfa.pending_secret', $this->mfa->genererSecret());
        $request->session()->put('mfa.pending_secret', $secret);

        return response()->json([
            'actif' => false,
            'obligatoire' => $this->mfa->estObligatoire($user),
            'secret' => $secret,
            'qr' => $this->mfa->qrDataUri($user, $secret),
        ]);
    }

    public function mfaSetupConfirm(MfaSetupRequest $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Une session navigateur est requise.', 'code' => 'session_requise'], 422);
        }

        $user = $request->user();
        $secret = $request->session()->get('mfa.pending_secret');

        if (! $secret || ! $this->mfa->verifierTotpAvecSecret($secret, $request->input('code'))) {
            return response()->json([
                'message' => 'Code incorrect. Rescannez le QR code si besoin.',
                'code' => 'mfa_invalid',
                'errors' => ['code' => ['Code incorrect.']],
            ], 422);
        }

        $codes = $this->mfa->genererCodesSecours();
        $this->mfa->activer($user, $secret, $codes);
        $request->session()->forget('mfa.pending_secret');
        AuditLogger::enregistrer('mfa_active', $user);

        return response()->json([
            'message' => 'Authentification à deux facteurs activée.',
            'actif' => true,
            'codes_secours' => $codes,
        ]);
    }

    public function mfaDisable(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($this->mfa->estObligatoire($user)) {
            return response()->json([
                'message' => 'La MFA est obligatoire pour votre rôle et ne peut pas être désactivée.',
                'code' => 'transition_invalide',
            ], 422);
        }

        $this->mfa->desactiver($user);
        if ($request->hasSession()) {
            $request->session()->forget('mfa.pending_secret');
        }
        AuditLogger::enregistrer('mfa_desactive', $user);

        return response()->json(['message' => 'Authentification à deux facteurs désactivée.', 'actif' => false]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLogger::enregistrer('logout', $user);
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        Auth::forgetGuards();

        return response()->json(['message' => 'Session clôturée.', 'code' => 'logged_out']);
    }

    private function finaliserSession(Request $request, User $user, bool $remember): JsonResponse
    {
        $request->session()->regenerate();
        Auth::login($user, $remember);
        $this->protection->reinitialiser($request->ip());
        $user->forceFill(['derniere_connexion_at' => now()])->save();
        $this->journaliser($request, $user, true);
        AuditLogger::enregistrer('login', $user);

        $status = $user->doit_changer_mot_de_passe ? 'password_change_required' : 'authenticated';

        return response()->json([
            'status' => $status,
            'code' => $status,
            'user' => $this->profil->pour($request),
        ]);
    }

    private function journaliser(Request $request, ?object $user, bool $succes, ?string $motif = null): void
    {
        LoginLog::create([
            'user_id' => $user?->id,
            'email' => $request->input('email') ?? $user?->email,
            'succes' => $succes,
            'motif' => $motif,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        if (! $succes) {
            \App\Support\JournalSecurite::echecConnexion((string) ($request->input('email') ?? $user?->email), (string) $motif);
        }
    }
}
