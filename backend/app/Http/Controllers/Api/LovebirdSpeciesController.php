<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LovebirdSpecies;
use App\Support\HeadToTailPhenotypeCatalog;
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
            ])
            ->map(function (LovebirdSpecies $row) {
                return [
                    'id' => $row->id,
                    'common_name' => $row->common_name,
                    'alternate_names' => $row->alternate_names,
                    'scientific_name' => $row->scientific_name,
                    'species_group' => $row->species_group,
                    'has_eye_ring' => $row->has_eye_ring,
                    'description' => $row->description,
                    'head_to_tail' => HeadToTailPhenotypeCatalog::forSpecies($row->id),
                ];
            })
            ->values();

        return response()->json([
            'data' => $species,
        ]);
    }
}
