<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\RoleLabels;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
        'role',
        'roles',
        'is_active',
    ];

    protected $appends = [
        'display_name',
        'role_labels',
        'avatar_url',
    ];

    // Связь с профилем спортсмена
    public function athlete()
    {
        return $this->hasOne(Athlete::class);
    }

    // Связь с профилем родителя
    public function guardian()
    {
        return $this->hasOne(Guardian::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getRolesList(): array
    {
        if (! empty($this->roles)) {
            return array_values($this->roles);
        }

        return $this->role ? [$this->role] : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRolesList(), true);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return (bool) array_intersect($roles, $this->getRolesList());
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->where('role', $role)
                ->orWhereJsonContains('roles', $role);
        });
    }

    public function syncRoles(array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles)));
        $this->roles = $roles;
        $this->role = $roles[0] ?? $this->role;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->relationLoaded('guardian') && $this->guardian?->full_name) {
            return $this->guardian->full_name;
        }

        if ($this->relationLoaded('athlete') && $this->athlete) {
            $a = $this->athlete;

            return trim("{$a->last_name_nom} {$a->first_name_nom} {$a->middle_name_nom}");
        }

        return $this->name;
    }

    public function getRoleLabelsAttribute(): string
    {
        return RoleLabels::labelsList($this->getRolesList());
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }
}
