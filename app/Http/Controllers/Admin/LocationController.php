<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\FormValidator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function index()
    {
        $search = request('search');

        return Inertia::render('Admin/Locations/Index', [
            'locations' => Location::query()
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('address', 'like', '%' . $search . '%');
                    });
                })
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        Location::create($validated);

        return back()->with('success', 'Зал добавлен');
    }

    public function update(Request $request, Location $location)
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $location->update($validated);

        return back()->with('success', 'Зал обновлен');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return back()->with('success', 'Зал удален');
    }
}
