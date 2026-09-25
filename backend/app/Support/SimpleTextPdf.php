<?php

namespace App\Support;

/**
 * Minimal multi-page PDF writer for prompt text. No extra Composer package.
 */
class SimpleTextPdf
{
    /**
     * @param  list<string>  $lines
     */
    public function render(string $title, array $lines): string
    {
        $pages = $this->paginate($lines, 48);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';

        $pageRefs = [];
        $nextId = 4;
        foreach ($pages as $pageLines) {
            $stream = $this->pageStream($title, $pageLines);
            $contentId = $nextId++;
            $pageId = $nextId++;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $pageRefs[] = $pageId.' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Count '.count($pageRefs).' /Kids ['.implode(' ', $pageRefs).'] >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $maxId = max(array_keys($objects));
        for ($id = 1; $id <= $maxId; $id++) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".($objects[$id] ?? '<< >>')."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".($maxId + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $lines
     * @return list<list<string>>
     */
    private function paginate(array $lines, int $perPage): array
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $chunks = str_split($line === '' ? ' ' : $line, 86);
            foreach ($chunks as $chunk) {
                $wrapped[] = rtrim($chunk);
            }
        }

        if ($wrapped === []) {
            $wrapped = ['(empty)'];
        }

        return array_chunk($wrapped, $perPage);
    }

    /**
     * @param  list<string>  $lines
     */
    private function pageStream(string $title, array $lines): string
    {
        $commands = "BT\n/F1 12 Tf\n50 800 Td\n(".$this->escape($title).") Tj\n/F1 9 Tf\n0 -22 Td\n";
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands .= "0 -14 Td\n";
            }
            $commands .= '('.$this->escape($line).") Tj\n";
        }
        $commands .= 'ET';

        return $commands;
    }

    private function escape(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $text = is_string($converted) && $converted !== '' ? $converted : $text;
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $text) ?? $text;
    }
}
