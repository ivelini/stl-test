<?php

namespace App\Http\Controllers\Holds;

use App\Http\Controllers\Controller;
use App\Http\Resources\HoldResource;
use App\Models\Hold;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HoldConfirmController extends Controller
{
    public function __invoke(Hold $hold): AnonymousResourceCollection
    {
        return HoldResource::collection(Hold::all());
    }
}
