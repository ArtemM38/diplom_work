<?php

namespace App\Support;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Support\Collection;

class GuardianChildAccess
{
    /**
     * @return Collection<int, array{id: int, full_name: string}>
     */
    public static function childrenForGuardian(User $user): Collection
    {
        $guardian = $user->guardian?->load('athletes');
        if (! $user->hasRole('guardian') || ! $guardian) {
            return collect();
        }

        return $guardian->athletes
            ->sortBy('last_name_nom')
            ->values()
            ->map(fn (Athlete $athlete) => [
                'id' => $athlete->id,
                'full_name' => self::fullName($athlete),
            ]);
    }

    public static function fullName(Athlete $athlete): string
    {
        return trim($athlete->last_name_nom.' '.$athlete->first_name_nom.' '.($athlete->middle_name_nom ?? ''));
    }

    public static function resolveChildId(User $user, ?int $requestedId): int
    {
        $children = self::childrenForGuardian($user);
        abort_if($children->isEmpty(), 404);

        $id = $requestedId ?: $children->first()['id'];
        abort_unless($children->pluck('id')->contains($id), 403);

        return $id;
    }
}
