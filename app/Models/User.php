<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\RoleLabels;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<int, string>|null */
    protected ?array $pendingRoleSync = null;

    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
        'is_active',
    ];

    protected $appends = [
        'display_name',
        'role_labels',
        'avatar_url',
        'role',
        'roles',
    ];

    public function athlete()
    {
        return $this->hasOne(Athlete::class);
    }

    public function guardian()
    {
        return $this->hasOne(Guardian::class);
    }

    public function roleModels(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('is_primary')
            ->withTimestamps()
            ->orderByDesc('role_user.is_primary');
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            unset($user->attributes['role'], $user->attributes['roles']);
        });

        static::saved(function (User $user) {
            if ($user->pendingRoleSync !== null) {
                $roles = $user->pendingRoleSync;
                $user->pendingRoleSync = null;
                $user->syncRoles($roles);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function extractRolesFromAttributes(array &$attributes): void
    {
        if (array_key_exists('roles', $attributes)) {
            $this->pendingRoleSync = array_values((array) $attributes['roles']);
            unset($attributes['roles']);
        } elseif (array_key_exists('role', $attributes)) {
            $this->pendingRoleSync = [$attributes['role']];
            unset($attributes['role']);
        }
    }

    public function fill(array $attributes)
    {
        $this->extractRolesFromAttributes($attributes);

        return parent::fill($attributes);
    }

    public function forceFill(array $attributes)
    {
        $this->extractRolesFromAttributes($attributes);

        return parent::forceFill($attributes);
    }

    /**
     * @return array<int, string>
     */
    public function getRolesList(): array
    {
        if ($this->relationLoaded('roleModels')) {
            return $this->roleModels->pluck('slug')->values()->all();
        }

        return $this->roleModels()->pluck('slug')->values()->all();
    }

    public function getRoleAttribute(): ?string
    {
        $roles = $this->getRolesList();

        return $roles[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function getRolesAttribute(): array
    {
        return $this->getRolesList();
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
        return $query->whereHas('roleModels', fn ($q) => $q->where('slug', $role));
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles)));
        $roleIds = Role::query()->whereIn('slug', $roles)->pluck('id', 'slug');

        $sync = [];
        foreach ($roles as $index => $slug) {
            if (! isset($roleIds[$slug])) {
                continue;
            }
            $sync[$roleIds[$slug]] = ['is_primary' => $index === 0];
        }

        $this->roleModels()->sync($sync);
        $this->unsetRelation('roleModels');
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
        return \App\Support\UserAvatar::url($this);
    }
}
