<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O motor do Graph View: pivô autorreferencial de `notes` para `notes`,
 * habilitando o bidirectional linking (backlinks).
 * Cada linha é uma aresta direcionada source -> target, criada quando o
 * usuário escreveu [[outra-nota]] no corpo de uma nota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_links', function (Blueprint $table) {
            // Nota onde o [[link]] foi digitado.
            $table->foreignId('source_note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            // Nota mencionada / alvo do link.
            $table->foreignId('target_note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            // Quando a conexão foi estabelecida (preenchido pelo banco).
            $table->timestamp('created_at')->useCurrent();

            // Aresta única por par (também impede duplicatas e auto-link A->A
            // é barrado na camada de aplicação).
            $table->primary(['source_note_id', 'target_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};