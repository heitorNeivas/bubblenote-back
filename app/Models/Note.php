<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Note extends Model
{
    /** @use HasFactory<\Database\Factories\NoteFactory> */
    use HasFactory;

    /**
     * Atributos preenchiveis via mass assignment.
     * `user_id` e intencionalmente omitido: o dono e sempre
     * derivado do usuario autenticado no controller.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body_markdown',
        'properties',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Frontmatter: entra/sai como array associativo, persiste como JSON.
            'properties' => 'array',
        ];
    }

    /**
     * Dono da nota.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tags associadas (N:N via tabela pivo `note_tag`).
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Notas para as quais esta nota aponta (outgoing links).
     * O pivo `note_links` tem apenas `created_at` (preenchido pelo banco).
     */
    public function linkedNotes(): BelongsToMany
    {
        return $this->belongsToMany(
            Note::class,
            'note_links',
            'source_note_id',
            'target_note_id',
        )->withPivot('created_at');
    }

    /**
     * Notas que apontam para esta nota (backlinks).
     */
    public function backlinks(): BelongsToMany
    {
        return $this->belongsToMany(
            Note::class,
            'note_links',
            'target_note_id',
            'source_note_id',
        )->withPivot('created_at');
    }
}