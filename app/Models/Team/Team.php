<?php

namespace App\Models\Team;

use App\Models\Agent\Agent;
use App\Models\User;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}
