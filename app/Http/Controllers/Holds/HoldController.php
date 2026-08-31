<?php

namespace App\Http\Controllers\Holds;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use App\Services\SlotService\Holds\CancelHold;
use Illuminate\Http\JsonResponse;

class HoldController extends Controller
{
    public function __construct(
        private readonly CancelHold $cancelHold,
    ) {}

    public function destroy(Hold $hold): JsonResponse
    {
        $this->cancelHold->handle($hold);

        return response()->json(status: 204);
    }
}
