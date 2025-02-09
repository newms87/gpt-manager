<?php

namespace App\Resources\TaskDefinition;

use App\Models\Task\TaskDefinitionAgent;
use App\Resources\Agent\AgentResource;
use App\Resources\Schema\SchemaAssociationResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;

class TaskDefinitionAgentResource extends ActionResource
{
	public static function data(TaskDefinitionAgent $taskAgent): array
	{
		return [
			'id'            => $taskAgent->id,
			'include_text'  => (bool)$taskAgent->include_text,
			'include_files' => (bool)$taskAgent->include_files,
			'include_data'  => (bool)$taskAgent->include_data,
			'created_at'    => $taskAgent->created_at,
			'updated_at'    => $taskAgent->updated_at,

			'agent'                   => fn($fields) => AgentResource::make($taskAgent->agent, $fields),
			'inputSchemaAssociations' => fn($fields) => SchemaAssociationResource::collection($taskAgent->inputSchemaAssociations, $fields),
			'outputSchemaAssociation' => fn($fields) => SchemaAssociationResource::make($taskAgent->outputSchemaAssociation, $fields),
		];
	}

	public static function details(Model $model, ?array $includeFields = null): array
	{
		return static::make($model, $includeFields ?? [
			'*'     => true,
			'agent' => [
				'name'  => true,
				'model' => true,
			],
		]);
	}
}
