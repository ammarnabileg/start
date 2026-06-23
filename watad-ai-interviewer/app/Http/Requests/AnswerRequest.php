<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // interview-session ownership is checked in the controller
    }

    public function rules(): array
    {
        return [
            'text'         => ['required', 'string', 'max:8000'],
            'client_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
