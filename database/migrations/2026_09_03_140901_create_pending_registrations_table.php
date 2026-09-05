<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastros aguardando confirmação de e-mail. O usuário só entra em `users`
 * depois que o código de 6 dígitos é validado — aqui ficam nome, e-mail,
 * senha (já hasheada) e o hash do código, com expiração de 15 min.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');   // já hasheada
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};