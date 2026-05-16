<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Guardian;
use App\Models\Rank;
use App\Models\RefereeCategory;
use App\Support\FullNameParser;
use App\Support\RussianNameCases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AthleteController extends Controller
{
    public function create()
    {
        $user = Auth::user();
        if ($user->hasRole('athlete') && $user->athlete) {
            return redirect()->route('dashboard');
        }

        $prefilledName = $user->hasRole('athlete') ? FullNameParser::parse($user->name) : null;
        $guardian = $user->guardian;

        return Inertia::render('Athlete/Create', [
            'ranks' => Rank::all(),
            'referee_categories' => RefereeCategory::all(),
            'existingGuardians' => Guardian::all(),
            'isParentRegistering' => $user->hasRole('guardian'),
            'prefilledName' => $prefilledName,
            'guardianRelation' => $guardian?->relation ?? '',
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->hasRole('athlete') && $user->athlete) {
            return redirect()->route('dashboard')->with('info', 'Анкета спортсмена уже создана');
        }

        $validated = $request->validate([
            'last_name_nom' => 'required|string',
            'first_name_nom' => 'required|string',
            'middle_name_nom' => 'nullable|string',
            'birth_date' => 'required|date|before_or_equal:today',
            'gender' => 'required|in:male,female',
            'phone' => 'nullable|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'registration_address' => 'nullable|string',
            'school_name' => 'nullable|string',
            'school_director_dat' => 'nullable|string',
            'school_class' => 'nullable|string',
            'work_place' => 'nullable|string',
            'work_position' => 'nullable|string',
            'photo' => 'nullable|file|image|max:4096',

            'guardian_id' => 'nullable|exists:guardians,id',
            'relation' => 'nullable|string|max:255',

            'ranks' => 'nullable|array',
            'ranks.*.rank_id' => 'required_with:ranks|exists:ranks,id',
            'ranks.*.assigned_at' => 'required_with:ranks|date',

            'referees' => 'nullable|array',
            'referees.*.referee_category_id' => 'required_with:referees|exists:referee_categories,id',
            'referees.*.assigned_at' => 'required_with:referees|date',

            'inventory' => 'nullable|array',
            'inventory.weapon_case' => 'sometimes|boolean',
            'inventory.jo' => 'sometimes|boolean',
            'inventory.boken' => 'sometimes|boolean',
            'inventory.tanto' => 'sometimes|boolean',
            'inventory.tshirt' => 'sometimes|boolean',
            'inventory.olympic_jacket' => 'sometimes|boolean',
            'inventory.cap' => 'sometimes|boolean',
            'inventory.backpack' => 'sometimes|boolean',
            'inventory.shoe_bag' => 'sometimes|boolean',
            'inventory.budo_passport' => 'sometimes|boolean',
            'inventory.qual_book' => 'sometimes|boolean',
            'inventory.referee_book' => 'sometimes|boolean',

            'doc_medical_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_medical_issue' => 'nullable|date|required_with:doc_medical_file,doc_medical_expiry',
            'doc_medical_expiry' => 'nullable|date|after_or_equal:doc_medical_issue|required_with:doc_medical_file,doc_medical_issue',
            'doc_insurance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_insurance_issue' => 'nullable|date|required_with:doc_insurance_file,doc_insurance_expiry',
            'doc_insurance_expiry' => 'nullable|date|after_or_equal:doc_insurance_issue|required_with:doc_insurance_file,doc_insurance_issue',
            'doc_identity_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_identity_series' => 'nullable|string|max:50|required_with:doc_identity_file,doc_identity_number,doc_identity_issued_by,doc_identity_issue_date',
            'doc_identity_number' => 'nullable|string|max:50|required_with:doc_identity_file,doc_identity_series,doc_identity_issued_by,doc_identity_issue_date',
            'doc_identity_issued_by' => 'nullable|string|max:255|required_with:doc_identity_file,doc_identity_series,doc_identity_number,doc_identity_issue_date',
            'doc_identity_issue_date' => 'nullable|date|required_with:doc_identity_file,doc_identity_series,doc_identity_number,doc_identity_issued_by',
        ]);

        DB::transaction(function () use ($request, $validated, $user) {
            $nameCases = RussianNameCases::buildFullNameCases(
                $validated['last_name_nom'],
                $validated['first_name_nom'],
                $validated['middle_name_nom'] ?? null
            );

            $athleteData = collect($validated)->only([
                'last_name_nom',
                'first_name_nom',
                'middle_name_nom',
                'phone',
                'birth_date',
                'gender',
                'registration_address',
                'school_name',
                'school_director_dat',
                'school_class',
                'work_place',
                'work_position',
            ])->toArray();
            $athleteData['full_name_gen'] = $nameCases['gen'];
            $athleteData['full_name_dat'] = $nameCases['dat'];
            $athleteData['full_name_ins'] = $nameCases['ins'];

            if ($user->hasRole('athlete')) {
                $athleteData['user_id'] = $user->id;
            }

            if ($request->hasFile('photo')) {
                $athleteData['photo'] = $request->file('photo')->store('athletes/photos', 'public');
            }

            $athlete = Athlete::create($athleteData);

            if ($request->guardian_id) {
                $athlete->guardians()->syncWithoutDetaching([$request->guardian_id]);
            } elseif ($user->hasRole('guardian')) {
                $guardian = $user->guardian;
                if ($guardian) {
                    $athlete->guardians()->syncWithoutDetaching([$guardian->id]);
                }
            }

            if (!empty($validated['ranks'])) {
                $athlete->rankHistories()->createMany($validated['ranks']);
            }

            if (!empty($validated['referees'])) {
                $athlete->refereeHistories()->createMany($validated['referees']);
            }

            $inventory = collect($validated['inventory'] ?? [])->only([
                'weapon_case',
                'jo',
                'boken',
                'tanto',
                'tshirt',
                'olympic_jacket',
                'cap',
                'backpack',
                'shoe_bag',
                'budo_passport',
                'qual_book',
                'referee_book',
            ])->toArray();
            $athlete->inventory()->create($inventory);

            if ($request->hasFile('doc_medical_file')) {
                $athlete->documents()->create([
                    'type' => 'medical',
                    'issue_date' => $validated['doc_medical_issue'] ?? null,
                    'expiry_date' => $validated['doc_medical_expiry'] ?? null,
                    'file_path' => $request->file('doc_medical_file')->store('athletes/documents', 'public'),
                ]);
            }

            if ($request->hasFile('doc_insurance_file')) {
                $athlete->documents()->create([
                    'type' => 'insurance',
                    'issue_date' => $validated['doc_insurance_issue'] ?? null,
                    'expiry_date' => $validated['doc_insurance_expiry'] ?? null,
                    'file_path' => $request->file('doc_insurance_file')->store('athletes/documents', 'public'),
                ]);
            }

            if ($request->hasFile('doc_identity_file')) {
                $athlete->documents()->create([
                    'type' => 'identity',
                    'series' => $validated['doc_identity_series'] ?? null,
                    'number' => $validated['doc_identity_number'] ?? null,
                    'issued_by' => $validated['doc_identity_issued_by'] ?? null,
                    'issue_date' => $validated['doc_identity_issue_date'] ?? null,
                    'file_path' => $request->file('doc_identity_file')->store('athletes/documents', 'public'),
                ]);
            }
        });

        return redirect()->route('dashboard')->with('success', 'Регистрация успешно завершена!');
    }

    public function edit(Athlete $athlete)
    {
        $user = Auth::user();
        $canEdit = $user && (
            $user->hasRole('admin')
            || $athlete->user_id === $user->id
            || ($user->hasRole('guardian') && $user->guardian?->athletes()->where('athletes.id', $athlete->id)->exists())
        );

        abort_unless($canEdit, 403);

        return Inertia::render('Athlete/Create', [
            'ranks' => Rank::all(),
            'referee_categories' => RefereeCategory::all(),
            'existingGuardians' => Guardian::all(),
            'isParentRegistering' => $user?->hasRole('guardian'),
            'editingAthlete' => $athlete->load(['rankHistories', 'refereeHistories', 'inventory', 'documents']),
            'submitRoute' => route('athlete.update', $athlete),
            'submitMethod' => 'patch',
            'cancelRoute' => $user?->hasRole('admin')
                ? route('admin.athletes.show', $athlete)
                : route('dashboard'),
        ]);
    }

    public function update(Request $request, Athlete $athlete)
    {
        $user = Auth::user();
        $canEdit = $user && (
            $user->hasRole('admin')
            || $athlete->user_id === $user->id
            || ($user->hasRole('guardian') && $user->guardian?->athletes()->where('athletes.id', $athlete->id)->exists())
        );
        abort_unless($canEdit, 403);

        $validated = $request->validate([
            'last_name_nom' => 'required|string',
            'first_name_nom' => 'required|string',
            'middle_name_nom' => 'nullable|string',
            'birth_date' => 'required|date|before_or_equal:today',
            'gender' => ['required', Rule::in(['male', 'female'])],
            'phone' => 'nullable|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'registration_address' => 'nullable|string',
            'school_name' => 'nullable|string',
            'school_director_dat' => 'nullable|string',
            'school_class' => 'nullable|string',
            'work_place' => 'nullable|string',
            'work_position' => 'nullable|string',
            'photo' => 'nullable|file|image|max:4096',
            'guardian_id' => 'nullable|exists:guardians,id',
            'relation' => 'nullable|string|max:255',
            'ranks' => 'nullable|array',
            'ranks.*.rank_id' => 'required_with:ranks|exists:ranks,id',
            'ranks.*.assigned_at' => 'required_with:ranks|date',
            'referees' => 'nullable|array',
            'referees.*.referee_category_id' => 'required_with:referees|exists:referee_categories,id',
            'referees.*.assigned_at' => 'required_with:referees|date',
            'inventory' => 'nullable|array',
            'inventory.weapon_case' => 'sometimes|boolean',
            'inventory.jo' => 'sometimes|boolean',
            'inventory.boken' => 'sometimes|boolean',
            'inventory.tanto' => 'sometimes|boolean',
            'inventory.tshirt' => 'sometimes|boolean',
            'inventory.olympic_jacket' => 'sometimes|boolean',
            'inventory.cap' => 'sometimes|boolean',
            'inventory.backpack' => 'sometimes|boolean',
            'inventory.shoe_bag' => 'sometimes|boolean',
            'inventory.budo_passport' => 'sometimes|boolean',
            'inventory.qual_book' => 'sometimes|boolean',
            'inventory.referee_book' => 'sometimes|boolean',
            'doc_medical_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_medical_issue' => 'nullable|date|required_with:doc_medical_file,doc_medical_expiry',
            'doc_medical_expiry' => 'nullable|date|after_or_equal:doc_medical_issue|required_with:doc_medical_file,doc_medical_issue',
            'doc_insurance_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_insurance_issue' => 'nullable|date|required_with:doc_insurance_file,doc_insurance_expiry',
            'doc_insurance_expiry' => 'nullable|date|after_or_equal:doc_insurance_issue|required_with:doc_insurance_file,doc_insurance_issue',
            'doc_identity_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:8192',
            'doc_identity_series' => 'nullable|string|max:50|required_with:doc_identity_file,doc_identity_number,doc_identity_issued_by,doc_identity_issue_date',
            'doc_identity_number' => 'nullable|string|max:50|required_with:doc_identity_file,doc_identity_series,doc_identity_issued_by,doc_identity_issue_date',
            'doc_identity_issued_by' => 'nullable|string|max:255|required_with:doc_identity_file,doc_identity_series,doc_identity_number,doc_identity_issue_date',
            'doc_identity_issue_date' => 'nullable|date|required_with:doc_identity_file,doc_identity_series,doc_identity_number,doc_identity_issued_by',
        ]);

        DB::transaction(function () use ($request, $validated, $athlete) {
            $nameCases = RussianNameCases::buildFullNameCases(
                $validated['last_name_nom'],
                $validated['first_name_nom'],
                $validated['middle_name_nom'] ?? null
            );

            $athleteData = collect($validated)->only([
                'last_name_nom',
                'first_name_nom',
                'middle_name_nom',
                'phone',
                'birth_date',
                'gender',
                'registration_address',
                'school_name',
                'school_director_dat',
                'school_class',
                'work_place',
                'work_position',
            ])->toArray();

            $athleteData['full_name_gen'] = $nameCases['gen'];
            $athleteData['full_name_dat'] = $nameCases['dat'];
            $athleteData['full_name_ins'] = $nameCases['ins'];

            if ($request->hasFile('photo')) {
                if ($athlete->photo) {
                    Storage::disk('public')->delete($athlete->photo);
                }
                $athleteData['photo'] = $request->file('photo')->store('athletes/photos', 'public');
            }

            $athlete->update($athleteData);

            if ($request->guardian_id) {
                $athlete->guardians()->syncWithoutDetaching([$request->guardian_id]);
            }

            $athlete->rankHistories()->delete();
            if (!empty($validated['ranks'])) {
                $athlete->rankHistories()->createMany($validated['ranks']);
            }

            $athlete->refereeHistories()->delete();
            if (!empty($validated['referees'])) {
                $athlete->refereeHistories()->createMany($validated['referees']);
            }

            $inventory = collect($validated['inventory'] ?? [])->only([
                'weapon_case',
                'jo',
                'boken',
                'tanto',
                'tshirt',
                'olympic_jacket',
                'cap',
                'backpack',
                'shoe_bag',
                'budo_passport',
                'qual_book',
                'referee_book',
            ])->toArray();
            $athlete->inventory()->updateOrCreate(['athlete_id' => $athlete->id], $inventory);

            if ($request->hasFile('doc_medical_file')) {
                $athlete->documents()->where('type', 'medical')->delete();
                $athlete->documents()->create([
                    'type' => 'medical',
                    'issue_date' => $validated['doc_medical_issue'] ?? null,
                    'expiry_date' => $validated['doc_medical_expiry'] ?? null,
                    'file_path' => $request->file('doc_medical_file')->store('athletes/documents', 'public'),
                ]);
            }

            if ($request->hasFile('doc_insurance_file')) {
                $athlete->documents()->where('type', 'insurance')->delete();
                $athlete->documents()->create([
                    'type' => 'insurance',
                    'issue_date' => $validated['doc_insurance_issue'] ?? null,
                    'expiry_date' => $validated['doc_insurance_expiry'] ?? null,
                    'file_path' => $request->file('doc_insurance_file')->store('athletes/documents', 'public'),
                ]);
            }

            if ($request->hasFile('doc_identity_file')) {
                $athlete->documents()->where('type', 'identity')->delete();
                $athlete->documents()->create([
                    'type' => 'identity',
                    'series' => $validated['doc_identity_series'] ?? null,
                    'number' => $validated['doc_identity_number'] ?? null,
                    'issued_by' => $validated['doc_identity_issued_by'] ?? null,
                    'issue_date' => $validated['doc_identity_issue_date'] ?? null,
                    'file_path' => $request->file('doc_identity_file')->store('athletes/documents', 'public'),
                ]);
            }
        });

        if ($user->hasAnyRole(['admin', 'coach', 'accountant'])) {
            return redirect()->route('admin.athletes.show', $athlete)->with('success', 'Данные спортсмена обновлены');
        }

        return redirect()->route('dashboard')->with('success', 'Данные спортсмена обновлены');
    }

    /**
     * Форма создания анкеты Родителя
     */
    public function createGuardian()
    {
        $user = Auth::user();

        return Inertia::render('Guardian/Create', [
            'prefilledFullName' => $user?->name ?? '',
        ]);
    }

    /**
     * Сохранение анкеты Родителя
     */
    public function storeGuardian(Request $request)
    {
        if (Auth::user()?->guardian) {
            return redirect()->route('athlete.create');
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'relation' => 'required|string', // Кем является (Отец/Мать)
        ]);

        Guardian::create([
            'user_id' => Auth::id(),
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'relation' => $validated['relation'],
        ]);

        Auth::user()->update(['name' => $validated['full_name']]);

        // После создания профиля родителя — сразу на создание анкеты ребенка
        return redirect()->route('athlete.create')->with('info', 'Теперь заполните данные вашего ребенка');
    }
}
