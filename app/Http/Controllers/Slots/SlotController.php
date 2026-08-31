<?php

namespace App\Http\Controllers\Slots;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlotResource;
use App\Models\Slot;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SlotController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SlotResource::collection(Slot::all());
    }
}
