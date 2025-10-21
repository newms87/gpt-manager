<?php

namespace App\Repositories;

use App\Models\Demand\DemandTemplate;
use App\Models\Demand\TemplateVariable;
use App\Services\Demand\TemplateVariableService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class TemplateVariableRepository extends ActionRepository
{
    public static string $model = TemplateVariable::class;

    public function query(): Builder
    {
        return parent::query()
            ->whereHas('demandTemplate', fn(Builder $builder) => $builder->where('team_id', team()->id))
            ->with(['demandTemplate', 'teamObjectSchemaAssociation.schemaDefinition', 'teamObjectSchemaAssociation.schemaFragment']);
    }

    /**
     * Handle actions on template variables
     */
    public function applyAction(string $action, Model|null|array $model = null, ?array $data = null)
    {
        if ($action === 'update' && $model instanceof TemplateVariable) {
            // Validate request data
            $validated = $this->validateUpdateData($data, $model);

            // Use service to update with business logic
            return app(TemplateVariableService::class)->updateVariable($model, $validated);
        }

        return parent::applyAction($action, $model, $data);
    }

    /**
     * Validate update request data
     */
    protected function validateUpdateData(array $data, TemplateVariable $variable): array
    {
        $rules = [
            'name'                              => ['prohibited'], // Name comes from Google Docs template and cannot be changed
            'description'                       => ['nullable', 'string'],
            'mapping_type'                      => ['sometimes', 'required', 'in:ai,artifact,team_object'],
            'artifact_categories'               => ['nullable', 'array'],
            'artifact_fragment_selector'        => ['nullable', 'array'],
            'team_object_schema_association_id' => ['nullable', 'integer', 'exists:schema_associations,id'],
            'schema_definition_id'              => ['nullable', 'integer', 'exists:schema_definitions,id'],
            'schema_fragment_id'                => ['nullable', 'integer', 'exists:schema_fragments,id'],
            'ai_instructions'                   => ['nullable', 'string'],
            'multi_value_strategy'              => ['nullable', 'in:join,first,unique,max,min,avg,sum'],
            'multi_value_separator'             => ['nullable', 'string', 'max:255'],
            'value_format_type'                 => ['nullable', 'in:text,integer,decimal,currency,percentage,date'],
            'decimal_places'                    => ['nullable', 'integer', 'min:0', 'max:4'],
            'currency_code'                     => ['nullable', 'string', 'size:3'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationError($validator->errors()->first(), 422);
        }

        return $validator->validated();
    }

    /**
     * Find variables for a specific template
     */
    public function findForTemplate(DemandTemplate $template): Collection
    {
        return $this->query()->where('demand_template_id', $template->id)->get();
    }
}
