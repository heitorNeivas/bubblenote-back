<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Em PATCH/PUT todos os campos sao opcionais ("sometimes"),
     * permitindo atualizacao parcial.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body_markdown' => ['sometimes', 'nullable', 'string'],
            'properties' => ['sometimes', 'nullable', 'array'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],

            'linked_note_ids' => ['sometimes', 'array'],
            'linked_note_ids.*' => ['integer', 'distinct'],
        ];
    }
}
