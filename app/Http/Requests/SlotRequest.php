<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SlotRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function idempotencyKey(): string
    {
        return $this->header('Idempotency-Key');
    }
}
