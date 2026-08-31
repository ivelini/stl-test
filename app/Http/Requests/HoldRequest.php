<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slot' => ['required', 'exists:slots'],
            'slot_id' => ['required', 'integer'],
            'idempotency_key' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
