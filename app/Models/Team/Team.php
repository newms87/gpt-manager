<?php

namespace App\Models\Team;

use App\Models\Agent\Agent;
use App\Models\Billing\BillingHistory;
use App\Models\Billing\PaymentMethod;
use App\Models\Billing\Subscription;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Models\User;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowInput;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Newms87\Danx\Models\Team\Team as DanxTeam;
use Override;

class Team extends DanxTeam
{
    #[Override]
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function agents(): HasMany|Agent
    {
        return $this->hasMany(Agent::class);
    }

    public function schemaDefinitions(): HasMany|SchemaDefinition
    {
        return $this->hasMany(SchemaDefinition::class);
    }

    public function taskDefinitions(): HasMany|TaskDefinition
    {
        return $this->hasMany(TaskDefinition::class);
    }

    public function workflowDefinitions(): HasMany|WorkflowDefinition
    {
        return $this->hasMany(WorkflowDefinition::class);
    }

    public function workflowInputs(): HasMany|WorkflowInput
    {
        return $this->hasMany(WorkflowInput::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function billingHistory(): HasMany
    {
        return $this->hasMany(BillingHistory::class);
    }
}
