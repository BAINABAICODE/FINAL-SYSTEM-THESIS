<?php

namespace App\Services\Breeding;

use App\Support\SimpleTextPdf;
use Illuminate\Support\Facades\Storage;

/**
 * Writes collected egg/chick image prompts to a PDF. Does not change genetics.
 */
class OffspringPromptPdfService
{
    public function __construct(
        private readonly OffspringImagePromptComposer $prompts,
        private readonly SimpleTextPdf $pdf,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $eggs
     * @return array{path: string, url: string, disk: string}
     */
    public function writeForComputation(int $computationResultId, array $eggs): array
    {
        $lines = $this->linesForEggs($eggs);
        $binary = $this->pdf->render('AGAPORA Image Prompts — Computation #'.$computationResultId, $lines);
        $path = 'offspring-visualizations/'.$computationResultId.'/image-prompts.pdf';
        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'disk' => 'public',
        ];
    }

    /**
     * Canonical instruction PDF for setup / thesis annex.
     *
     * @return array{path: string, url: string}
     */
    public function writeInstructionManual(): array
    {
        $lines = explode("\n", $this->manualText());
        $binary = $this->pdf->render('AGAPORA Image Prompt Instructions', $lines);
        $path = 'agapora/AGAPORA-image-prompt-instructions.pdf';
        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $eggs
     * @return list<string>
     */
    private function linesForEggs(array $eggs): array
    {
        $lines = explode("\n", $this->prompts->instructionText());
        $lines[] = '';
        $lines[] = 'API: OpenRouter Image API (OPENROUTER_API_KEY in backend/.env)';
        $lines[] = 'Endpoint: https://openrouter.ai/api/v1/images';
        $lines[] = 'Model: '.config('services.openrouter.image_model', 'google/gemini-3-pro-image');
        $lines[] = 'Fallback: Hugging Face if OpenRouter credits are exhausted';
        $lines[] = '';

        foreach (array_values($eggs) as $index => $egg) {
            if (isset($egg['egg_status']) && $egg['egg_status'] !== ClutchSimulationService::STATUS_LIVING_CHICK) {
                $lines[] = str_repeat('-', 72);
                $lines[] = 'EGG #'.($egg['egg_number'] ?? ($index + 1)).' — '.($egg['egg_status_label'] ?? $egg['egg_status']);
                $lines[] = 'No image prompt: this simulated egg did not produce a living chick.';
                $lines[] = '';
                continue;
            }
            $composed = is_array($egg['image_prompt'] ?? null) ? $egg['image_prompt'] : $this->prompts->compose($egg);
            $collected = $composed['collected'] ?? $this->prompts->collect($egg);
            $number = $collected['egg_number'] ?? ($index + 1);
            $lines[] = str_repeat('-', 72);
            $lines[] = 'EGG / CHICK OUTCOME #'.$number;
            $lines[] = 'SPECIES: '.($collected['species'] ?? 'Not recorded');
            $lines[] = 'BASE COLOR: '.($collected['base_color'] ?? 'Not recorded');
            $lines[] = 'VISUAL MUTATION: '.$this->join($collected['visual_mutations'] ?? []);
            $lines[] = 'SPLIT AND HIDDEN GENES: '.$this->join($collected['split_hidden_genes'] ?? []);
            $lines[] = '';
            $lines[] = 'COMPOSED PROMPT SENT TO IMAGE API:';
            foreach (explode("\n", (string) ($composed['prompt'] ?? $egg['composed_image_prompt'] ?? '')) as $promptLine) {
                $lines[] = $promptLine;
            }
            $lines[] = '';
            $lines[] = 'SHORT API PROMPT:';
            $lines[] = (string) ($composed['prompt_short'] ?? $egg['image_prompt_short'] ?? '');
            $lines[] = '';
        }

        return $lines;
    }

    private function manualText(): string
    {
        return implode("\n", [
            $this->prompts->instructionText(),
            '',
            'PROMPT TEMPLATE',
            '',
            $this->prompts->templatePrompt([
                'species' => '{SPECIES}',
                'scientific_name' => '{SCIENTIFIC_NAME}',
                'sex' => '{SEX}',
                'base_color' => '{BASE COLOR}',
                'visual_mutations' => ['{VISUAL MUTATION}'],
                'split_hidden_genes' => ['{SPLIT AND HIDDEN GENES}'],
            ]),
            '',
            'EXAMPLE (Fischer cock, green, visual SL Greywing, hidden Opaline and Dilute)',
            '',
            $this->prompts->templatePrompt([
                'species' => "Fischer's lovebird",
                'scientific_name' => 'Agapornis fischeri',
                'sex' => 'Male / Cock',
                'base_color' => 'Green',
                'visual_mutations' => ['SL Greywing'],
                'split_hidden_genes' => ['Opaline', 'Dilute'],
            ]),
            '',
            'SETUP (OpenRouter image generation)',
            '1. In backend/.env set OFFSPRING_IMAGE_ENABLED=true',
            '2. OFFSPRING_IMAGE_PROVIDER=openrouter',
            '3. Set OPENROUTER_API_KEY from https://openrouter.ai/keys',
            '4. php artisan storage:link',
            '5. Restart Laravel, then Analyze a pair.',
            '6. Each egg collects SPECIES, BASE COLOR, VISUAL MUTATION, SPLIT AND HIDDEN GENES.',
            '7. AGAPORA composes the prompt, calls OpenRouter, stores the PNG, and writes this PDF.',
            '8. If OpenRouter credits run out, Hugging Face is used as fallback.',
        ]);
    }

    /**
     * @param  list<string>  $names
     */
    private function join(array $names): string
    {
        return $names === [] ? 'None' : implode(', ', $names);
    }
}
