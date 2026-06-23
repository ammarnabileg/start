<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public route; invitation validity is checked in the controller
    }

    public function rules(): array
    {
        $maxKb = (int) config('watad.uploads.cv_max_kb');
        $mimes = implode(',', config('watad.uploads.cv_mimes'));

        return [
            'full_name'        => ['required', 'string', 'max:190'],
            'email'            => ['required', 'email', 'max:190'],
            'phone'            => ['nullable', 'string', 'max:40'],
            'linkedin_url'     => ['nullable', 'url', 'max:512'],
            'country'          => ['nullable', 'string', 'max:80'],
            'years_experience' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'expected_salary'  => ['nullable', 'numeric', 'min:0'],
            'salary_currency'  => ['nullable', 'string', 'size:3'],
            'notice_period'    => ['nullable', 'string', 'max:60'],
            'cv'               => ['required', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
            'consent'          => ['accepted'],
        ];
    }
}
