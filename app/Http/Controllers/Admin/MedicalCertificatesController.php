<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AthleteDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicalCertificatesController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $filter = $request->input('filter', 'all');
        $search = $request->input('search');

        $query = AthleteDocument::query()
            ->with(['athlete:id,last_name_nom,first_name_nom,middle_name_nom,phone'])
            ->where('type', 'medical')
            ->whereNotNull('expiry_date');

        if ($search) {
            $query->whereHas('athlete', function ($q) use ($search) {
                $q->where('last_name_nom', 'like', '%' . $search . '%')
                    ->orWhere('first_name_nom', 'like', '%' . $search . '%');
            });
        }

        $allDocuments = $query->orderBy('expiry_date')->get()->map(function (AthleteDocument $doc) {
            $expiry = Carbon::parse($doc->expiry_date)->startOfDay();
            $today = now()->startOfDay();
            $daysLeft = $today->diffInDays($expiry, false);

            $status = 'ok';
            if ($daysLeft < 0) {
                $status = 'expired';
            } elseif ($daysLeft <= 3) {
                $status = 'warning';
            }

            $athlete = $doc->athlete;

            return [
                'id' => $doc->id,
                'athlete_id' => $doc->athlete_id,
                'full_name' => $athlete
                    ? trim("{$athlete->last_name_nom} {$athlete->first_name_nom} " . ($athlete->middle_name_nom ?? ''))
                    : '—',
                'phone' => $athlete?->phone,
                'issue_date' => $doc->issue_date,
                'expiry_date' => $doc->expiry_date,
                'days_left' => $daysLeft,
                'status' => $status,
            ];
        });

        $summary = [
            'expired' => $allDocuments->where('status', 'expired')->count(),
            'warning' => $allDocuments->where('status', 'warning')->count(),
            'ok' => $allDocuments->where('status', 'ok')->count(),
        ];

        $documents = $allDocuments;
        if ($filter === 'expired') {
            $documents = $documents->where('status', 'expired')->values();
        } elseif ($filter === 'warning') {
            $documents = $documents->where('status', 'warning')->values();
        } elseif ($filter === 'ok') {
            $documents = $documents->where('status', 'ok')->values();
        }

        return Inertia::render('Admin/MedicalCertificates/Index', [
            'documents' => $documents,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'filter' => $filter,
            ],
        ]);
    }
}
