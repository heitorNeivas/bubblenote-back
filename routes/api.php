<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas publicas
|--------------------------------------------------------------------------
| Fluxo SPA: o frontend chama GET /sanctum/csrf-cookie (rota registrada
| automaticamente pelo Sanctum) antes do primeiro POST, e envia o header
| X-XSRF-TOKEN nas requisicoes seguintes.
|
| Cadastro em 2 passos: /register grava o pendente e envia o codigo;
| /register/verify valida o codigo e SO ENTAO cria o usuario + devolve o token.
*/
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])
    ->middleware('throttle:verify-code')
    ->name('register.verify');
Route::post('/register/resend', [AuthController::class, 'resendRegistrationCode'])
    ->middleware('throttle:resend-code')
    ->name('register.resend');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Rotas protegidas (sessao/cookie do Sanctum — guard "web")
|--------------------------------------------------------------------------
| O middleware statefulApi() (bootstrap/app.php) inicia a sessao para as
| origens em SANCTUM_STATEFUL_DOMAINS; "auth:sanctum" entao autentica pela
| sessao, sem necessidade de token Bearer.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('notes', NoteController::class);
});

/*
|--------------------------------------------------------------------------
| Route model binding com escopo de dono
|--------------------------------------------------------------------------
| Resolve {note} apenas dentro das notas do usuario autenticado.
| Id inexistente OU pertencente a outro usuario => 404 (nao vaza existencia).
*/
Route::bind('note', function (string $value) {
    $userId = request()->user()?->id;

    return Note::query()
        ->when($userId, fn ($query) => $query->where('user_id', $userId))
        ->findOrFail($value);
});