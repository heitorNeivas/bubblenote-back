<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O `register` NÃO cria o usuário — grava um cadastro pendente e envia o
 * código. O `users` só nasce em `/api/register/verify` (ver
 * RegistrationVerificationTest).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_grava_pendente_e_nao_cria_usuario(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana Dev',
            'email' => 'ana@example.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('verification_required', true)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
        $this->assertDatabaseHas('pending_registrations', ['email' => 'ana@example.com']);
        $this->assertGuest();
    }

    public function test_email_normalizado_no_cadastro_pendente(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => '  AnA@Example.COM ',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertStatus(202);

        $this->assertDatabaseHas('pending_registrations', ['email' => 'ana@example.com']);
    }

    public function test_email_de_usuario_ativo_e_rejeitado(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Outra Ana',
            'email' => 'ana@example.com',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_senha_sem_confirmacao_e_rejeitada(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana Dev',
            'email' => 'ana@example.com',
            'password' => 'senha-forte-123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('pending_registrations', ['email' => 'ana@example.com']);
    }

    public function test_senha_curta_e_rejeitada(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana Dev',
            'email' => 'ana@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}