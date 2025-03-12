<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Traits\ActionModelTrait;

class ObjectTag extends Model
{
    use SoftDeletes, ActionModelTrait;

    protected $fillable = [
        'name',
        'category',
    ];

    public function __toString()
    {
        return "<ObjectTag ($this->id) $this->name>";
    }
}
