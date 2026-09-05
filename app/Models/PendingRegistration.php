<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Cadastro aguardando confirmação de e-mail. Guarda o hash do código de
 * 6 dígitos; nunca o código em claro. Expira em 15 min.
 */
class PendingRegistration extends Model
{
    public const TTL_MINUTES = 15;

    protected $fillable = ['name', 'email', 'password', 'code_hash', 'expires_at'];

    protected $hidden = ['password', 'code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /**
     * Cria (ou substitui) o cadastro pendente do e-mail e devolve o código
     * EM CLARO, para envio por e-mail. `$plainPassword` é hasheada aqui.
     */
    public static function issue(string $name, string $email, string $plainPassword): string
    {
        static::where('expires_at', '<', now())->delete(); // poda abandonados

        $code = self::randomCode();

        static::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($plainPassword),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ],
        );

        return $code;
    }

    /**
     * Gera um novo código para este cadastro pendente (sem tocar na senha),
     * renova a expiração e devolve o código em claro.
     */
    public function regenerateCode(): string
    {
        $code = self::randomCode();

        $this->update([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $code;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    private static function randomCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }
}