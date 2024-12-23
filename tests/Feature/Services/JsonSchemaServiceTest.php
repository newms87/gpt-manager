<?php

namespace Tests\Feature\Services;

use App\Services\JsonSchema\JsonSchemaService;
use Tests\AuthenticatedTestCase;

class JsonSchemaServiceTest extends AuthenticatedTestCase
{
	public function test_formatAndCleanSchema_citedValuesAddedToSchema(): void
	{
		// Given
		$name   = 'person';
		$schema = [
			'type'       => 'object',
			'title'      => 'Person',
			'properties' => [
				'name' => [
					'type' => 'string',
				],
				'dob'  => [
					'type' => 'string',
				],
			],
		];

		// When
		$formattedResponse = app(JsonSchemaService::class)->useCitations()->formatAndCleanSchema($name, $schema);

		// Then
		$this->assertEquals([
			'name'   => $name,
			'strict' => true,
			'schema' => [
				'type'                 => 'object',
				'title'                => 'Person',
				'properties'           => [
					'name' => ['$ref' => '#/$defs/citation'],
					'dob'  => ['$ref' => '#/$defs/citation'],
				],
				'$defs'                => ['citation' => JsonSchemaService::$citationDef],
				'required'             => ['name', 'dob'],
				'additionalProperties' => false,
			],
		], $formattedResponse);
	}
}
