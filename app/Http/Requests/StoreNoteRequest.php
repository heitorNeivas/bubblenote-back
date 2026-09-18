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

            'properties' => ['sometimes', 'nullable', 'array'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],

            'linked_note_ids' => ['sometimes', 'array'],
            'linked_note_ids.*' => ['integer', 'distinct'],
        ];
    }
}
