<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Guardian;
use App\Models\Rank;
use App\Models\RefereeCategory;
use App\Support\FullNameParser;
use App\Support\AthleteProfileRules;
use App\Support\FormValidator;
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

        $validated = FormValidator::validate(
            $request,
            AthleteProfileRules::rules(),
            AthleteProfileRules::messages(),
        );

        $validated = $this->applyOccupationFields($validated);

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
                'occupation_type',
                'registration_address',
                'school_name',
                'school_director_dat',
                'school_class',
                'kindergarten_name',
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
                    'identity_kind' => $validated['doc_identity_kind'] ?? null,
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
                : route('profile.edit'),
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

        $validated = FormValidator::validate(
            $request,
            AthleteProfileRules::rules(),
            AthleteProfileRules::messages(),
        );

        $validated = $this->applyOccupationFields($validated);

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
                'occupation_type',
                'registration_address',
                'school_name',
                'school_director_dat',
                'school_class',
                'kindergarten_name',
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
                    'identity_kind' => $validated['doc_identity_kind'] ?? null,
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

        if ($user->hasRole('athlete') && $athlete->user_id === $user->id) {
            return redirect()->route('profile.edit')->with('success', 'Данные спортсмена обновлены');
        }

        if ($user->hasRole('guardian')) {
            return redirect()->route('profile.edit')->with('success', 'Данные спортсмена обновлены');
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

        $validated = FormValidator::validate($request, [
            'full_name' => 'required|string|max:255',
            'phone' => 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            'relation' => 'required|string|max:255',
        ], [
            'phone.regex' => 'Телефон укажите в формате +7 (999) 999-99-99.',
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyOccupationFields(array $validated): array
    {
        if (($validated['occupation_type'] ?? '') === 'study') {
            $validated['work_place'] = null;
            $validated['work_position'] = null;
            $validated['kindergarten_name'] = null;
        } elseif (($validated['occupation_type'] ?? '') === 'work') {
            $validated['school_name'] = null;
            $validated['school_director_dat'] = null;
            $validated['school_class'] = null;
            $validated['kindergarten_name'] = null;
        } elseif (($validated['occupation_type'] ?? '') === 'kindergarten') {
            $validated['school_name'] = null;
            $validated['school_director_dat'] = null;
            $validated['school_class'] = null;
            $validated['work_place'] = null;
            $validated['work_position'] = null;
        }

        return $validated;
    }
}
