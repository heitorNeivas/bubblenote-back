<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_usuario_pode_logar_com_credenciais_corretas(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-correta-1')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'senha-correta-1',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_com_senha_errada_falha(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-correta-1')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'errada',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_e_bloqueado_apos_cinco_tentativas(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-correta-1')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'errada'])
                ->assertStatus(422);
        }

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'errada']);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'Muitas tentativas',
            $response->json('errors.email.0')
        );
    }

    public function test_usuario_autenticado_pode_deslogar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout efetuado com sucesso.');
    }

    public function test_rotas_protegidas_exigem_autenticacao(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/notes')->assertUnauthorized();
    }

    public function test_falha_de_auth_sem_header_accept_responde_401_json(): void
    {
        // Request "crua" (sem Accept: application/json), como um fetch
        // server-to-server do BFF. Antes retornava 500 "Route [login] not defined".
        $response = $this->get('/api/user');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Não autenticado.');
    }

    public function test_token_bearer_invalido_responde_401_json(): void
    {
        $this->get('/api/user', ['Authorization' => 'Bearer token-invalido'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Não autenticado.');
    }

    public function test_login_funciona_independente_da_grafia_do_email(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => Hash::make('senha-forte-1'),
        ]);

        // Login com outra grafia (maiúsculas + espaços) ainda encontra o usuário.
        $this->postJson('/api/login', [
            'email' => '  Ana@EXAMPLE.com  ',
            'password' => 'senha-forte-1',
        ])->assertOk()->assertJsonPath('user.email', 'ana@example.com');
    }
}
