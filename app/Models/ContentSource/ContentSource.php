<?php

namespace App\Models\ContentSource;

use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;

class ContentSource extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    const
        TYPE_API = 'api',
        TYPE_RSS = 'rss';

    protected $fillable = [
        'name',
        'type',
        'url',
        'config',
        'per_page',
        'polling_interval',
    ];

    public function casts()
    {
        return [
            'config' => 'json',
        ];
    }

    public function workflowInputs()
    {
        return $this->hasMany(WorkflowInput::class);
    }

    /**
     * @return static
     * @throws ValidationException
     */
    public function validate(): static
    {
        validator($this->toArray(), [
            'name'    => [
                'required',
                'max:80',
                'string',
                Rule::unique('content_sources')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'team_id' => 'required|integer',
            'type'    => 'required|string',
            'url'     => 'string|url',
        ])->validate();

        return $this;
    }

    public function __toString()
    {
        return "<ContentSource ($this->id) $this->name>";
    }
}
