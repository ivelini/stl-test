<?php

namespace App\Models;

use App\Enums\HoldStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    protected function casts(): array
    {
        return [
            'idempotency_key' => 'string',
            'expires_at' => 'datetime',
            'status' => HoldStatusEnum::class,
        ];
    }

    protected $fillable = [
        'slot_id',
        'status',
        'idempotency_key',
        'expires_at',
    ];

    /** @return BelongsTo<Slot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
