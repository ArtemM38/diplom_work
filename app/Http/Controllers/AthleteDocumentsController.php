<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Support\AthleteDocumentGenerator;
use App\Support\GuardianChildAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AthleteDocumentsController extends Controller
{
    public function templates(Request $request)
    {
        return response()->json([
            'templates' => AthleteDocumentGenerator::templateList(),
        ]);
    }

    public function download(Request $request, int $template)
    {
        abort_unless(array_key_exists($template, config('athlete_document_templates.templates', [])), 404);

        $athlete = $this->resolveAthlete($request);
        $defaultFormat = config("athlete_document_templates.templates.{$template}.extension", 'docx');
        $format = $request->string('format')->toString() ?: $defaultFormat;
        $rules = $this->validationRules($template);
        $extra = $rules !== [] ? $request->validate($rules) : [];

        $allowed = config("athlete_document_templates.templates.{$template}.formats", ['docx']);
        abort_unless(in_array($format, $allowed, true), 422, 'Недопустимый формат файла.');

        return app(AthleteDocumentGenerator::class)->download($template, $athlete, $format, $extra, $request->user());
    }

    private function resolveAthlete(Request $request): Athlete
    {
        $user = $request->user();

        if ($user->hasRole('athlete') && $user->athlete) {
            return $user->athlete->load(['guardians.user', 'groups', 'finance', 'documents']);
        }

        if ($user->hasRole('guardian')) {
            $athleteId = GuardianChildAccess::resolveChildId(
                $user,
                $request->integer('athlete_id') ?: null,
            );

            return Athlete::with(['guardians.user', 'groups', 'finance', 'documents'])->findOrFail($athleteId);
        }

        if ($user->hasAnyRole(['admin', 'coach', 'accountant'])) {
            $athleteId = $request->integer('athlete_id');
            abort_unless($athleteId, 422, 'Укажите спортсмена.');

            return Athlete::with(['guardians.user', 'groups', 'finance', 'documents'])->findOrFail($athleteId);
        }

        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(int $template): array
    {
        $fields = config("athlete_document_templates.constructor_fields.{$template}", []);
        $rules = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $rule = ($field['required'] ?? false) ? 'required' : 'nullable';

            $rules[$name] = match ($field['type'] ?? 'text') {
                'date' => "{$rule}|date",
                'number' => "{$rule}|numeric|min:0",
                'textarea' => "{$rule}|string|max:2000",
                default => "{$rule}|string|max:500",
            };
        }

        return $rules;
    }
}
