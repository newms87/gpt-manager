<?php

namespace App\Models\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    public function roles(): BelongsToMany|Role
    {
        return $this->belongsToMany(Role::class, 'role_permission')->withTimestamps();
    }
}
