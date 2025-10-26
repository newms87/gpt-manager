<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class Knowledge extends Model implements AuditableContract
{
    use AuditableTrait, HasFactory, SoftDeletes;

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}
