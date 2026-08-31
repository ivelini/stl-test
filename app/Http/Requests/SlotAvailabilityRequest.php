<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SlotAvailabilityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function page(): int
    {
        return (int) $this->input('page', 1);
    }
}
