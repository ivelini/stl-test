<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use App\Http\Requests\SlotRequest;
use App\Http\Resources\SlotResource;
use App\Models\Slot;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SlotHoldController extends Controller
{
    public function __invoke(SlotRequest $request): AnonymousResourceCollection
    {
        return SlotResource::collection(Slot::all());
    }
}
