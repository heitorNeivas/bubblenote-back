<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de 6 dígitos para verificação de e-mail no cadastro.
 * Guarda só o hash do código; expira em 15 min; um por usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('user_id'); // 1 código ativo por usuário (reenviar substitui)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};