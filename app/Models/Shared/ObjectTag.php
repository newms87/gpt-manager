<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ObjectTag extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category',
    ];

    public function __toString()
    {
        return "<ObjectTag ($this->id) $this->name>";
    }
}
