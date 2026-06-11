<?php

namespace App\Support;

use App\Models\Athlete;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use ZipArchive;

class AthleteDocumentGenerator
{
    public function download(
        int $template,
        Athlete $athlete,
        string $format,
        array $extra = [],
        ?User $user = null,
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response {
        $meta = config("athlete_document_templates.templates.{$template}");
        abort_unless($meta, 404);

        $sourceExt = $meta['extension'] ?? 'docx';
        $sourcePath = $this->resolveTemplatePath($template, $sourceExt);
        $filledPath = $this->fillTemplate($template, $sourcePath, $sourceExt, $athlete, $extra, $user);

        $basename = 'Приложение-' . $template . '-' . now()->format('Ymd-His');

        if ($format === 'pdf') {
            $response = $this->convertSourceToPdf($filledPath, $sourceExt, "{$basename}.pdf");
            if ($filledPath !== $sourcePath) {
                @unlink($filledPath);
            }

            return $response;
        }

        if ($template === 8 && $format === 'xlsx') {
            $tempXlsx = $this->convertXlsToXlsx($filledPath);
            if ($filledPath !== $sourcePath) {
                @unlink($filledPath);
            }

            return response()->download($tempXlsx, "{$basename}.xlsx")->deleteFileAfterSend(true);
        }

        if ($filledPath !== $sourcePath) {
            return response()->download($filledPath, "{$basename}.{$sourceExt}")->deleteFileAfterSend(true);
        }

        $temp = $this->tempFile($sourceExt);
        copy($sourcePath, $temp);

        return response()->download($temp, "{$basename}.{$sourceExt}")->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function fillTemplate(
        int $template,
        string $sourcePath,
        string $sourceExt,
        Athlete $athlete,
        array $extra,
        ?User $user,
    ): string {
        $fillConfig = config("athlete_document_templates.fill.{$template}", []);
        if ($fillConfig === []) {
            return $sourcePath;
        }

        $variables = AthleteDocumentVariables::build($athlete, $extra, $user);

        if ($sourceExt === 'docx') {
            return app(DocxTemplateFiller::class)->fill($sourcePath, $variables, $fillConfig);
        }

        if (in_array($sourceExt, ['xls', 'xlsx'], true)) {
            return app(SpreadsheetTemplateFiller::class)->fill($sourcePath, $sourceExt, $variables, $fillConfig);
        }

        return $sourcePath;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function templateList(): array
    {
        $out = [];
        foreach (config('athlete_document_templates.templates', []) as $id => $meta) {
            $out[] = [
                'id' => (int) $id,
                'title' => $meta['title'],
                'formats' => $meta['formats'],
                'constructor' => (bool) ($meta['constructor'] ?? false),
                'fields' => config("athlete_document_templates.constructor_fields.{$id}", []),
            ];
        }

        return $out;
    }

    private function resolveTemplatePath(int $template, string $ext): string
    {
        if ($template === 6 && ! file_exists($this->templateFile(6, 'docx'))) {
            $this->bootstrapTemplateSix();
        }

        $path = $this->templateFile($template, $ext);
        abort_unless(file_exists($path), 404, 'Файл шаблона не найден. Обратитесь к администратору.');

        return $path;
    }

    private function templateFile(int $template, string $ext): string
    {
        return storage_path("app/document-templates/template-{$template}.{$ext}");
    }

    private function bootstrapTemplateSix(): void
    {
        $source = $this->templateFile(5, 'docx');
        $target = $this->templateFile(6, 'docx');
        copy($source, $target);
    }

    private function tempFile(string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'athlete-doc-');
        $target = $path . '.' . $ext;
        if (file_exists($path)) {
            unlink($path);
        }

        return $target;
    }

    private function convertXlsToXlsx(string $xlsPath): string
    {
        $spreadsheet = IOFactory::load($xlsPath);
        $xlsx = $this->tempFile('xlsx');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsx);

        return $xlsx;
    }

    private function convertSourceToPdf(
        string $sourcePath,
        string $sourceExt,
        string $downloadName
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\Response
    {
        $officePdf = $this->convertToPdfViaOfficeCom($sourcePath, $sourceExt);
        if ($officePdf && file_exists($officePdf)) {
            return response()->download($officePdf, $downloadName)->deleteFileAfterSend(true);
        }

        $htmlPath = $this->tempFile('html');

        if ($sourceExt === 'docx') {
            $phpWord = WordIOFactory::load($sourcePath);
            WordIOFactory::createWriter($phpWord, 'HTML')->save($htmlPath);
        } elseif (in_array($sourceExt, ['xls', 'xlsx'], true)) {
            $spreadsheet = IOFactory::load($sourcePath);
            IOFactory::createWriter($spreadsheet, 'Html')->save($htmlPath);
        } else {
            abort(422, 'Невозможно конвертировать файл в PDF.');
        }

        $html = file_get_contents($htmlPath) ?: '';
        @unlink($htmlPath);

        return Pdf::loadHTML($html)->setPaper('a4')->download($downloadName);
    }

    private function convertToPdfViaOfficeCom(string $sourcePath, string $sourceExt): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $mode = $sourceExt === 'docx' ? 'word' : (in_array($sourceExt, ['xls', 'xlsx'], true) ? 'excel' : null);
        if (! $mode) {
            return null;
        }

        $scriptPath = $this->tempFile('ps1');
        $pdfPath = $this->tempFile('pdf');

        $script = <<<'PS1'
param(
    [Parameter(Mandatory = $true)][string] $SourcePath,
    [Parameter(Mandatory = $true)][string] $OutputPath,
    [Parameter(Mandatory = $true)][string] $Mode
)
$ErrorActionPreference = "Stop"

if ($Mode -eq "word") {
    $app = $null
    $doc = $null
    try {
        $app = New-Object -ComObject Word.Application
        $app.Visible = $false
        $app.DisplayAlerts = 0
        $doc = $app.Documents.Open($SourcePath, $false, $true)
        $wdFormatPDF = 17
        $doc.SaveAs([ref]$OutputPath, [ref]$wdFormatPDF)
        $doc.Close()
        $app.Quit()
    } finally {
        if ($doc -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($doc) }
        if ($app -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($app) }
    }
    exit 0
}

if ($Mode -eq "excel") {
    $app = $null
    $wb = $null
    try {
        $app = New-Object -ComObject Excel.Application
        $app.Visible = $false
        $app.DisplayAlerts = $false
        $wb = $app.Workbooks.Open($SourcePath, 0, $true)
        $xlTypePDF = 0
        $wb.ExportAsFixedFormat($xlTypePDF, $OutputPath)
        $wb.Close($false)
        $app.Quit()
    } finally {
        if ($wb -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($wb) }
        if ($app -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($app) }
    }
    exit 0
}

exit 1
PS1;

        file_put_contents($scriptPath, $script);

        $cmd = sprintf(
            'powershell -NoProfile -ExecutionPolicy Bypass -File %s -SourcePath %s -OutputPath %s -Mode %s',
            escapeshellarg($scriptPath),
            escapeshellarg($sourcePath),
            escapeshellarg($pdfPath),
            escapeshellarg($mode),
        );
        @shell_exec($cmd);
        @unlink($scriptPath);

        if (file_exists($pdfPath) && filesize($pdfPath) > 0) {
            return $pdfPath;
        }

        @unlink($pdfPath);
        return null;
    }

}
