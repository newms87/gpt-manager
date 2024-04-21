<?php

namespace App\Models\Agent;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Knowledge extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}
