<?php

namespace App\Models\Team;

use App\Models\User;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
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
}
