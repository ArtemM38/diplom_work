<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AddressSuggestionController extends Controller
{
    public function suggest(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3|max:255',
        ]);

        $token = config('services.dadata.token');
        if (!$token) {
            return response()->json(['suggestions' => []]);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', [
            'query' => $request->string('query')->toString(),
            'count' => 8,
        ]);

        if ($response->failed()) {
            return response()->json(['suggestions' => []], 200);
        }

        return response()->json([
            'suggestions' => collect($response->json('suggestions', []))
                ->map(fn($item) => ['value' => $item['value'] ?? null])
                ->filter(fn($item) => !empty($item['value']))
                ->values(),
        ]);
    }
}
