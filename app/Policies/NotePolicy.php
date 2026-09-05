<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Regra unica de negocio: um usuario so enxerga e manipula
 * as proprias notas. Todos os metodos derivam disso.
 */
class NotePolicy
{
    /**
     * Listagem (o filtro real por dono e feito na query do controller).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }
}
