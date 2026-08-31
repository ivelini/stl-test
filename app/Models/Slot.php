<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $confirmed_count Агрегат from withCount: количество подтверждённых удержаний.
 *
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<Slot>
 */
class Slot extends Model
{
    use HasFactory;

    protected $fillable = [
        'capacity',
    ];

    /** @return HasMany<Hold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }
}
