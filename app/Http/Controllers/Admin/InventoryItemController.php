<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Support\FormValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        return Inertia::render('Admin/InventoryItems/Index', [
            'items' => InventoryItem::query()
                ->when($search, fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
        ]);

        $baseSlug = Str::slug($validated['name'], '_');
        $slug = $baseSlug !== '' ? $baseSlug : 'item';
        $suffix = 1;
        while (InventoryItem::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $suffix;
            $suffix++;
        }

        $maxSort = (int) InventoryItem::max('sort_order');

        InventoryItem::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Позиция инвентаря добавлена');
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $validated = FormValidator::validate($request, [
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $inventoryItem->update($validated);

        return back()->with('success', 'Позиция инвентаря обновлена');
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        if ($inventoryItem->athletes()->exists()) {
            return back()->withErrors([
                'inventory' => 'Нельзя удалить позицию: она выдана спортсменам. Отключите её вместо удаления.',
            ]);
        }

        $inventoryItem->delete();

        return back()->with('success', 'Позиция инвентаря удалена');
    }
}
