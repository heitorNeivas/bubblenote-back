<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    /**
     * A tabela `tags` não tem timestamps (só id, user_id, name).
     */
    public $timestamps = false;

    /**
     * `user_id` fica fora do fillable: é definido pela relação
     * `$user->tags()->firstOrCreate(...)` no controller.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Dono da tag (isolamento por usuário).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notas que possuem esta tag (N:N via `note_tag`).
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class);
    }
}