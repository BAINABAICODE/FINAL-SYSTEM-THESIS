<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComputationResult;
use App\Services\Breeding\BreedingAnalysisService;
use App\Services\Breeding\OffspringPromptPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComputationResultController extends Controller
{
    public function __construct(
        private readonly BreedingAnalysisService $analysis,
        private readonly OffspringPromptPdfService $promptPdf,
    ) {}

    public function show(ComputationResult $computationResult): JsonResponse
    {
        return response()->json([
            'data' => $this->analysis->present($computationResult),
        ]);
    }

    public function downloadImagePrompts(ComputationResult $computationResult): StreamedResponse
    {
        $eggs = $computationResult->offspring_visualizations ?? [];
        $path = $eggs[0]['image_prompt_pdf']['path']
            ?? 'offspring-visualizations/'.$computationResult->id.'/image-prompts.pdf';

        if (! Storage::disk('public')->exists($path)) {
            $written = $this->promptPdf->writeForComputation((int) $computationResult->id, is_array($eggs) ? $eggs : []);
            $path = $written['path'];
        }

        return Storage::disk('public')->download(
            $path,
            'AGAPORA-image-prompts-'.$computationResult->id.'.pdf',
        );
    }
}
