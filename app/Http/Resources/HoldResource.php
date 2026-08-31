<?php

namespace App\Http\Resources;

use App\Models\Hold;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Hold */
class HoldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slot_id' => $this->slot_id,
            'idempotency_key' => $this->idempotency_key,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'slot' => $this->whenLoaded('slot', fn () => new SlotResource($this->slot)),
        ];
    }
}
