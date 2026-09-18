<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PendingRegistration extends Model
{
    public const TTL_MINUTES = 15;

    protected $fillable = ['name', 'email', 'password', 'code_hash', 'expires_at'];

    protected $hidden = ['password', 'code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public static function issue(string $name, string $email, string $plainPassword): string
    {
        static::where('expires_at', '<', now())->delete();

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