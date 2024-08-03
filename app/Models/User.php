<?php

namespace App\Models;

use App\Models\Team\Team;
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

    public function delete()
    {
        $this->teams()->detach();

        return parent::delete();
    }
}
