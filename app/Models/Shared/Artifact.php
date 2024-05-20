<?php

namespace App\Models\Shared;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Helpers\ArrayHelper;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artifact extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function groupContentBy($key)
    {
        $data = json_decode($this->content, true);

        return ArrayHelper::groupByDot($data, $key);
    }

    public function __toString()
    {
        return "<Artifact ($this->id) $this->name>";
    }
}
