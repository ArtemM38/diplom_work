<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $http = Http::withHeaders([
            'Authorization' => 'Token ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        // На некоторых локальных Windows-сборках OpenSSL не доверяет цепочке DaData.
        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        try {
            $response = $http->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', [
                'query' => $request->string('query')->toString(),
                'count' => 8,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DaData request failed', ['message' => $e->getMessage()]);
            return response()->json(['suggestions' => []], 200);
        }

        if ($response->failed()) {
            return response()->json(['suggestions' => []], 200);
        }

        return response()->json([
            'suggestions' => collect($response->json('suggestions', []))
                ->map(fn ($item) => ['value' => $item['value'] ?? null])
                ->filter(fn ($item) => ! empty($item['value']))
                ->values(),
        ]);
    }

    public function suggestCity(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $token = config('services.dadata.token');
        if (! $token) {
            return response()->json(['suggestions' => []]);
        }

        $http = Http::withHeaders([
            'Authorization' => 'Token ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        try {
            $response = $http->post('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address', [
                'query' => $request->string('query')->toString(),
                'count' => 8,
                'from_bound' => ['value' => 'city'],
                'to_bound' => ['value' => 'city'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('DaData city request failed', ['message' => $e->getMessage()]);

            return response()->json(['suggestions' => []], 200);
        }

        if ($response->failed()) {
            return response()->json(['suggestions' => []], 200);
        }

        return response()->json([
            'suggestions' => collect($response->json('suggestions', []))
                ->map(fn ($item) => [
                    'value' => $item['data']['city'] ?? $item['value'] ?? null,
                ])
                ->filter(fn ($item) => ! empty($item['value']))
                ->unique('value')
                ->values(),
        ]);
    }
}
