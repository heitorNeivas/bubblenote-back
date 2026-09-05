<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Rota publica: qualquer visitante pode se registrar.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza o e-mail antes de validar/gravar: o PostgreSQL compara com
     * case-sensitivity, entao cadastro e login precisam usar a mesma forma.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => trim(mb_strtolower((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // `confirmed` exige o campo `password_confirmation`.
            // Password::defaults() e configurado em AppServiceProvider.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
