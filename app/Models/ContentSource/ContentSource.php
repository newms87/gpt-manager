<?php

namespace App\Models\ContentSource;

use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class ContentSource extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasRelationCountersTrait, SoftDeletes;

    const string
        TYPE_API = 'api',
        TYPE_RSS = 'rss';

    protected $fillable = [
        'name',
        'type',
        'url',
        'config',
        'per_page',
        'last_checkpoint',
        'polling_interval',
    ];

    public array $relationCounters = [
        WorkflowInput::class => ['workflowInputs' => 'workflow_inputs_count'],
    ];

    public function casts(): array
    {
        return [
            'config'     => 'json',
            'fetched_at' => 'datetime',
        ];
    }

    public function workflowInputs(): HasMany|WorkflowInput
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
