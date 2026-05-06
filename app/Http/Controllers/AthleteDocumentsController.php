<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AthleteDocumentsController extends Controller
{
    private function getAthleteOrFail()
    {
        $athlete = Auth::user()?->athlete;
        abort_unless($athlete, 404, 'Профиль спортсмена не найден');
        return $athlete;
    }

    private function templateTitle(int $template): string
    {
        return match ($template) {
            1 => 'Приложение 1. Заявление на участие',
            2 => 'Приложение 2. Согласие на обработку персональных данных',
            3 => 'Приложение 3. Согласие на участие в тренировочном процессе',
            default => 'Приложение 4. Заявление родителя/законного представителя',
        };
    }

    public function downloadPdf(Request $request, int $template)
    {
        abort_unless(in_array($template, [1, 2, 3, 4], true), 404);
        $athlete = $this->getAthleteOrFail();

        $pdf = Pdf::loadView('pdf.athlete-template', [
            'athlete' => $athlete,
            'title' => $this->templateTitle($template),
            'template' => $template,
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download('template-' . $template . '-' . now()->format('Ymd-His') . '.pdf');
    }

    public function downloadWord(Request $request, int $template)
    {
        abort_unless(in_array($template, [1, 2, 3, 4], true), 404);
        $athlete = $this->getAthleteOrFail();

        $html = view('word.athlete-template', [
            'athlete' => $athlete,
            'title' => $this->templateTitle($template),
            'template' => $template,
            'generatedAt' => now(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-' . $template . '.doc"',
        ]);
    }
}
