<?php

namespace App\Console\Commands;

use App\Services\Breeding\OffspringPromptPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WriteImagePromptManual extends Command
{
    protected $signature = 'agapora:write-image-prompt-pdf';

    protected $description = 'Write the AGAPORA image-prompt instruction PDF (no genetics calculation).';

    public function handle(OffspringPromptPdfService $pdf): int
    {
        $written = $pdf->writeInstructionManual();
        $absolute = storage_path('app/public/'.$written['path']);
        $docs = base_path('../docs/AGAPORA-Image-Prompt-Instructions.pdf');
        File::ensureDirectoryExists(dirname($docs));
        File::copy($absolute, $docs);
        $this->info('Wrote '.$absolute);
        $this->info('Copied '.$docs);

        return self::SUCCESS;
    }
}
