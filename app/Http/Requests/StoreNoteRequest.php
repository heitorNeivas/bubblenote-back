<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    /**
     * A autorizacao de acesso ao recurso e feita via middleware
     * `auth:sanctum` na rota e via Policy no controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body_markdown' => ['nullable', 'string'],

            // Frontmatter estruturado (status, due, aliases, ...): objeto livre.
            'properties' => ['sometimes', 'nullable', 'array'],

            // Tags opcionais: array de nomes ("inbox", "projeto-x", ...).
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],

            // Links opcionais: ids de outras notas do proprio usuario.
            'linked_note_ids' => ['sometimes', 'array'],
            'linked_note_ids.*' => ['integer', 'distinct'],
        ];
    }
}
