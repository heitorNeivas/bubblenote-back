<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `verification_codes` (código pós-cadastro, com o usuário já gravado) foi
 * substituída por `pending_registrations` — o usuário só nasce após verificar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('verification_codes');
    }

    public function down(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique('user_id');
        });
    }
};
