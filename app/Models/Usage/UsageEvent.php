<?php

namespace App\Models\Usage;

use App\Models\Team\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsageEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function team(): Team|BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): User|BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
