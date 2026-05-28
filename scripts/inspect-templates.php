<?php

require __DIR__ . '/../vendor/autoload.php';

$dir = __DIR__ . '/../storage/app/document-templates';

foreach (glob($dir . '/template-*') as $path) {
    echo "\n=== " . basename($path) . " ===\n";
    $ext = pathinfo($path, PATHINFO_EXTENSION);

    if ($ext === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            preg_match_all('/\$\{[^}]+\}/', $xml, $m);
            $vars = array_unique($m[0] ?? []);
            if ($vars) {
                echo implode("\n", $vars) . "\n";
            } else {
                $text = strip_tags(str_replace(['<w:tab/>', '</w:p>'], ["\t", "\n"], $xml));
                echo mb_substr($text, 0, 800) . "\n";
            }
        }
    } elseif (in_array($ext, ['xlsx', 'xls'], true)) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        for ($r = 1; $r <= min(20, $sheet->getHighestRow()); $r++) {
            $row = [];
            for ($c = 1; $c <= min(10, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn())); $c++) {
                $v = $sheet->getCell([$c, $r])->getCalculatedValue();
                if ($v !== null && $v !== '') {
                    $row[] = $v;
                }
            }
            if ($row) {
                echo implode(' | ', $row) . "\n";
            }
        }
    } else {
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        echo $element->getText() . "\n";
                    }
                }
            }
        } catch (Throwable $e) {
            echo 'Error: ' . $e->getMessage() . "\n";
        }
    }
}
