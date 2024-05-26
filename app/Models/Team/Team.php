<?php

namespace App\Models\Team;

use App\Models\Agent\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class Team extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    public function users(): BelongsToMany|User
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function agents(): HasMany|Agent
    {
        return $this->hasMany(Agent::class);
    }
}
