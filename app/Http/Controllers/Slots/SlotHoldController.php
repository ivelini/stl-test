<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use App\Http\Requests\SlotRequest;
use App\Http\Resources\HoldResource;
use App\Models\Slot;
use App\Services\SlotService\Holds\CreateHold;
use Illuminate\Http\JsonResponse;

class SlotHoldController extends Controller
{
    public function __construct(
        private readonly CreateHold $createHold,
    ) {}

    public function __invoke(SlotRequest $request, Slot $slot): JsonResponse
    {
        $hold = $this->createHold->handle($slot, $request->idempotencyKey());

        return new HoldResource($hold)
            ->response()
            ->setStatusCode($hold->wasRecentlyCreated ? 201 : 200);
    }
}
