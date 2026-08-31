<?php

namespace App\Http\Controllers\Slots;

use App\Cache\FlexibleCache;
use App\Http\Controllers\Controller;
use App\Http\Requests\SlotAvailabilityRequest;
use App\Services\SlotService\Slots\AvailabilityReader;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function __construct(
        private readonly FlexibleCache $cache,
        private readonly AvailabilityReader $reader,
    ) {}

    public function index(SlotAvailabilityRequest $request): JsonResponse
    {
        $map = $this->cache->handle(
            config('availability.cache_key'),
            config('availability.cache_window'),
            fn () => $this->reader->handle(),
        );

        $total = count($map);
        $perPage = (int) config('availability.per_page');
        $page = $request->page();

        $data = array_slice($map, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }
}
