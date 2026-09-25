<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BreedingPrediction;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = BreedingPrediction::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BreedingPrediction $record) => $this->summary($record));

        return response()->json(['data' => $items]);
    }

    public function show(BreedingPrediction $prediction): JsonResponse
    {
        return response()->json(['data' => $this->detail($prediction)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(BreedingPrediction $record): array
    {
        return [
            'id' => $record->id,
            'parent_1_bird_id' => $record->parent_1_bird_id,
            'parent_2_bird_id' => $record->parent_2_bird_id,
            'parent_1' => $record->parent_1,
            'parent_2' => $record->parent_2,
            'status' => $record->status,
            'compatibility_status' => $record->validation['compatibility']['compatibility_status'] ?? null,
            'compatibility_label' => $record->validation['compatibility']['label'] ?? null,
            'computation_result_id' => $record->computation_result_id,
            'created_at' => $record->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(BreedingPrediction $record): array
    {
        return [
            ...$this->summary($record),
            'validation' => $record->validation,
            'prediction' => $record->prediction,
        ];
    }
}
