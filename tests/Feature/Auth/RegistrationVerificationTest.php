<?php

namespace Tests\Feature\Auth;

use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verificação do cadastro: o usuário só é gravado em `users` quando o código
 * de 6 dígitos é confirmado em POST /api/register/verify. Testes de integração
 * reais — banco de verdade, sem fakes (phpunit.xml já usa MAIL_MAILER=array).
 */
class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fluxo_completo_register_verify_cria_o_usuario(): void
    {
        // 1. Cadastro pendente (não cria usuário).
        $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertStatus(202);

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);

        // 2. Só o hash do código é guardado — fixamos um conhecido para o teste.
        PendingRegistration::where('email', 'ana@example.com')
            ->update(['code_hash' => Hash::make('654321')]);

        // 3. Verifica: agora sim o usuário nasce, já verificado.
        $response = $this->postJson('/api/register/verify', [
            'email' => 'ana@example.com',
            'code' => '654321',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.email_verified', true);

        $user = User::where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseMissing('pending_registrations', ['email' => 'ana@example.com']);

        // 4. O token devolvido dá acesso aos recursos.
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/notes')->assertOk();

        // 5. A senha do pending foi preservada (login funciona).
        $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'senha-forte-123',
        ])->assertOk();
    }

    public function test_codigo_errado_nao_cria_usuario(): void
    {
        PendingRegistration::issue('Ana', 'ana@example.com', 'senha-forte-123');

        $this->postJson('/api/register/verify', [
            'email' => 'ana@example.com',
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('message', 'Código inválido.');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseHas('pending_registrations', ['email' => 'ana@example.com']);
    }

    public function test_codigo_expirado_e_rejeitado(): void
    {
        PendingRegistration::issue('Ana', 'ana@example.com', 'senha-forte-123');
        PendingRegistration::where('email', 'ana@example.com')
            ->update(['code_hash' => Hash::make('654321'), 'expires_at' => now()->subMinute()]);

        $this->postJson('/api/register/verify', [
            'email' => 'ana@example.com',
            'code' => '654321',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Cadastro não encontrado ou expirado. Faça o cadastro novamente.');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    public function test_email_sem_cadastro_pendente_e_rejeitado(): void
    {
        $this->postJson('/api/register/verify', [
            'email' => 'ninguem@example.com',
            'code' => '123456',
        ])->assertStatus(422);
    }

    public function test_codigo_aceito_como_numero_ou_sem_zero_a_esquerda(): void
    {
        PendingRegistration::issue('Ana', 'ana@example.com', 'senha-forte-123');
        PendingRegistration::where('email', 'ana@example.com')
            ->update(['code_hash' => Hash::make('042315')]);

        $this->postJson('/api/register/verify', [
            'email' => 'ana@example.com',
            'code' => 42315, // número, sem o zero à esquerda
        ])->assertStatus(201)->assertJsonPath('user.email_verified', true);
    }

    public function test_verificar_email_ja_ativo_retorna_409(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/register/verify', [
            'email' => 'ana@example.com',
            'code' => '123456',
        ])->assertStatus(409);
    }

    public function test_resend_gera_novo_codigo_para_o_pendente(): void
    {
        PendingRegistration::issue('Ana', 'ana@example.com', 'senha-forte-123');
        $antes = PendingRegistration::where('email', 'ana@example.com')->value('code_hash');

        $this->postJson('/api/register/resend', ['email' => 'ana@example.com'])
            ->assertStatus(202);

        $depois = PendingRegistration::where('email', 'ana@example.com')->value('code_hash');
        $this->assertNotSame($antes, $depois);
    }

    public function test_resend_para_email_desconhecido_responde_generico(): void
    {
        $this->postJson('/api/register/resend', ['email' => 'ninguem@example.com'])
            ->assertStatus(202);
    }

    public function test_formato_invalido_e_rejeitado(): void
    {
        PendingRegistration::issue('Ana', 'ana@example.com', 'senha-forte-123');

        $this->postJson('/api/register/verify', ['email' => 'ana@example.com', 'code' => 'abc'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe o e-mail e o código de 6 dígitos.');
    }
}