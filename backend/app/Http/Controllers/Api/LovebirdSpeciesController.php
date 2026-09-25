<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LovebirdSpecies;
use Illuminate\Http\JsonResponse;

class LovebirdSpeciesController extends Controller
{
    public function index(): JsonResponse
    {
        $species = LovebirdSpecies::query()
            ->orderBy('id')
            ->get([
                'id',
                'common_name',
                'alternate_names',
                'scientific_name',
                'species_group',
                'has_eye_ring',
                'description',
            ]);

        return response()->json([
            'data' => $species,
        ]);
    }
}
