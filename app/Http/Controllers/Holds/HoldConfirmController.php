<?php

namespace App\Http\Controllers\Holds;

use App\Http\Controllers\Controller;
use App\Http\Resources\HoldResource;
use App\Models\Hold;
use App\Services\SlotService\Holds\ConfirmHold;

class HoldConfirmController extends Controller
{
    public function __construct(
        private readonly ConfirmHold $confirmHold,
    ) {}

    public function __invoke(Hold $hold): HoldResource
    {
        $this->confirmHold->handle($hold);

        return new HoldResource($hold->fresh());
    }
}
