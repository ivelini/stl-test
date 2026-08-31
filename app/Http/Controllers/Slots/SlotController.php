<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use App\Http\Requests\SlotAvailabilityRequest;
use App\Services\SlotService\Slots\ReadAvailabilityPage;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function __construct(
        private readonly ReadAvailabilityPage $availabilityPage,
    ) {}

    public function index(SlotAvailabilityRequest $request): JsonResponse
    {
        return response()->json($this->availabilityPage->handle($request->page()));
    }
}
