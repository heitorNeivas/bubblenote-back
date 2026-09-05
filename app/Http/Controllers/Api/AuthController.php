<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\VerifyEmailCodeQueued;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Autenticacao hibrida do Sanctum:
 *  - SPA (Next.js): requisicao stateful (header Origin de SANCTUM_STATEFUL_DOMAINS)
 *    -> login por cookie de sessao; a resposta traz "token": null.
 *  - Cliente de API (Insomnia/Postman/mobile): sem sessao -> a resposta traz um
 *    token pessoal para usar em "Authorization: Bearer <token>".
 *
 * Regra: o usuario so entra em `users` DEPOIS de confirmar o e-mail. Ate la o
 * cadastro fica em `pending_registrations`.
 */
class AuthController extends Controller
{
    /**
     * POST /api/register
     *
     * Nao cria o usuario: guarda o cadastro pendente e envia o codigo.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $code = PendingRegistration::issue($data['name'], $data['email'], $data['password']);

        // Notificacao "on-demand" — ainda nao ha usuario/Notifiable.
        Notification::route('mail', $data['email'])
            ->notify(new VerifyEmailCodeQueued($code));

        return response()->json([
            'message' => 'Enviamos um código de 6 dígitos para o seu e-mail. Confirme para ativar a conta.',
            'verification_required' => true,
        ], 202);
    }

    /**
     * POST /api/register/verify   { email, code }   (publica)
     *
     * Valida o codigo e SO ENTAO cria o usuario (ja verificado) + devolve o token.
     */
    public function verifyRegistration(Request $request): JsonResponse
    {
        $email = trim(mb_strtolower((string) $request->input('email')));
        $digits = preg_replace('/\D/', '', (string) $request->input('code'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $digits === '' || strlen($digits) > 6) {
            return response()->json(['message' => 'Informe o e-mail e o código de 6 dígitos.'], 422);
        }

        $code = str_pad($digits, 6, '0', STR_PAD_LEFT);

        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'Essa conta já foi ativada. Faça login.'], 409);
        }

        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending || $pending->isExpired()) {
            return response()->json([
                'message' => 'Cadastro não encontrado ou expirado. Faça o cadastro novamente.',
            ], 422);
        }

        if (! $pending->matches($code)) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        try {
            $user = DB::transaction(function () use ($pending) {
                $user = User::create([
                    'name' => $pending->name,
                    'email' => $pending->email,
                    'password' => $pending->password, // ja hasheada; o cast nao re-hasheia
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $pending->delete();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Essa conta já foi ativada. Faça login.'], 409);
        }

        event(new Registered($user));

        $token = $this->establishAuth($request, $user);

        return response()->json([
            'message' => 'Conta ativada com sucesso.',
            'user' => $this->publicUser($user),
            'token' => $token,
        ], 201);
    }

    /**
     * POST /api/register/resend   { email }   (publica)
     */
    public function resendRegistrationCode(Request $request): JsonResponse
    {
        $email = trim(mb_strtolower((string) $request->input('email')));

        $pending = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? PendingRegistration::where('email', $email)->first()
            : null;

        if ($pending && ! $pending->isExpired()) {
            Notification::route('mail', $email)
                ->notify(new VerifyEmailCodeQueued($pending->regenerateCode()));
        }

        // Resposta generica — nao revela se ha cadastro pendente para o e-mail.
        return response()->json([
            'message' => 'Se houver um cadastro pendente para esse e-mail, enviamos um novo código.',
        ], 202);
    }

    /**
     * POST /api/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user('web');
        $token = $this->establishAuth($request, $user);

        return response()->json([
            'message' => 'Autenticado com sucesso.',
            'user' => $this->publicUser($user),
            'token' => $token,
        ]);
    }

    /**
     * POST /api/logout — encerra a sessao (SPA) ou revoga o token atual (API).
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $request->user()?->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logout efetuado com sucesso.']);
    }

    /**
     * Se a requisicao e stateful (SPA), loga pela sessao e devolve null.
     * Caso contrario, emite um token pessoal do Sanctum e o devolve.
     */
    private function establishAuth(Request $request, User $user): ?string
    {
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return null;
        }

        return $user->createToken('api')->plainTextToken;
    }

    /**
     * Projecao segura do usuario para a resposta.
     *
     * @return array<string, mixed>|null
     */
    private function publicUser(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
        ] : null;
    }
}