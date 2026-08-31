<?php

namespace App\Http\Controllers\Holds;

use App\Http\Controllers\Controller;
use App\Models\Hold;
use Illuminate\Http\JsonResponse;

class HoldController extends Controller
{
    public function destroy(Hold $hold): JsonResponse
    {
        $hold->delete();

        return response()->json();
    }
}
