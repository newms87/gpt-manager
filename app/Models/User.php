<?php

namespace App\Models;

use App\Models\Authorization\Role;
use App\Models\Team\Team;
use Cache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public ?Team $currentTeam = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function teams(): BelongsToMany|Team
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function delete(): ?bool
    {
        $this->teams()->detach();

        return parent::delete();
    }

    public function can($abilities, $arguments = []): bool
    {
        $abilities = (array)$abilities;

        foreach($abilities as $ability) {
            if ($this->hasPermission($ability)) {
                return true;
            }
        }

        return false;
    }

    public function roles(): BelongsToMany|Role
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return (bool)$this->getCachedPermissions()[$role] ?? false;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getCachedPermissions();

        foreach($permissions as $rolePermissions) {
            if (in_array($permission, $rolePermissions)) {
                return true;
            }
        }

        return false;
    }

    public function getCachedPermissions()
    {
        return Cache::remember(
            "user_permissions:$this->id",
            now()->addDay(),
            fn() => $this->roles()
                ->with('permissions')
                ->get()
                ->map(fn(Role $role) => $role->permissions->pluck('name')->toArray())
                ->keyBy('name')
        );
    }

    public function forgetCachedPermissions(): void
    {
        Cache::forget("user_permissions:$this->id");
    }

    public function setCurrentTeam($name): static
    {
        $this->currentTeam = $name ? $this->teams()->firstWhere('name', $name) : null;

        return $this;
    }
}
