<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\HeadToTailPhenotypeCatalog;
use Illuminate\Http\JsonResponse;

class HeadToTailPhenotypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => HeadToTailPhenotypeCatalog::indexPayload(),
        ]);
    }
}
