<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entidade de Conhecimento (o núcleo). Todo conteúdo é atrelado a um usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // Isolamento: 1 usuário tem N notas. Apagar o usuário apaga as notas.
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            // Texto cru, com marcações (#, **, [[ ]]).
            $table->text('body_markdown')->nullable();

            // Frontmatter estruturado (status, data de validade, etc.).
            // No SQLite é armazenado como TEXT; consultável via JSON functions.
            $table->json('properties')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};