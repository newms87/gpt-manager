<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Traits\AuditableTrait;

class Artifact extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function groupContentBy($key): array
    {
        $data = json_decode($this->content, true);

        $groups = ArrayHelper::groupByDot($data, $key);

        $groupByWithKeys = [];
        foreach($groups as $key => $group) {
            if (is_array($group)) {
                $groupByWithKeys[$key] = $group;
            } else {
                $groupKey                   = match (true) {
                    is_int($group) => $group,
                    is_string($group) => strlen($group) > 20 ? substr($group, 0, 10) . '|' . substr(md5($group), 0, 10) : $group,
                    is_bool($group) => $group ? 'true' : 'false',
                    default => substr(md5(json_encode($group)), 0, 20) . ' was here',
                };
                $groupByWithKeys[$groupKey] = $groupKey;
            }
        }

        return $groupByWithKeys;
    }

    public function __toString()
    {
        return "<Artifact ($this->id) $this->name>";
    }
}
