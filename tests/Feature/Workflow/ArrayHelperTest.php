<?php

namespace Tests\Feature\Workflow;

use Newms87\Danx\Helpers\ArrayHelper;
use Tests\AuthenticatedTestCase;

class ArrayHelperTest extends AuthenticatedTestCase
{
    const array TEST_DATA = [
        'name'    => 'Dan Newman',
        'dob'     => '1987-11-18',
        'aliases' => ['The Hammer', 'Daniel'],
        'weapons' => ['Sledge Hammer', 'Keyboard'],
        'address' => [
            'city'  => 'Denver',
            'state' => 'CO',
            'zip'   => '80031',
        ],
        'powers'  => [
            [
                'name'  => 'Hammer',
                'power' => 50,
                'uses'  => ['smash', 'pound'],
                'specs' => [['name' => 'mana', 'value' => 30], ['name' => 'cooldown', 'value' => 5]],
            ],
            [
                'name'  => 'Code',
                'power' => 80,
                'uses'  => ['compile', 'debug'],
                'specs' => [['name' => 'mana', 'value' => 50], ['name' => 'cooldown', 'value' => 10]],
            ],
            [
                'name'  => 'Slap',
                'power' => 50,
                'uses'  => ['slap', 'poke'],
                'specs' => [['name' => 'mana', 'value' => 30], ['name' => 'cooldown', 'value' => 2]],
            ],
        ],
    ];

    /*****************************
     * extractNestedData Tests
     *****************************/
    public function test_extractNestedData_producesEmptyArrayFromStringArtifact(): void
    {
        // Given
        $artifact       = 'string artifact';
        $includedFields = ['name'];

        // When
        $includedData = ArrayHelper::extractNestedData($artifact, $includedFields);

        // Then
        $this->assertNull($includedData);
    }

    public function test_extractNestedData_nonExistingScalarReturnsNull(): void
    {
        // Given
        $artifact       = ['dob' => '1987-11-18'];
        $includedFields = ['name'];

        // When
        $includedData = ArrayHelper::extractNestedData($artifact, $includedFields);

        // Then
        $this->assertEquals(null, $includedData);
    }

    public function test_extractNestedData_producesIdenticalArtifactWhenIncludedFieldsEmpty(): void
    {
        // Given
        $includedFields = [];

        // When
        $includedData = ArrayHelper::extractNestedData(self::TEST_DATA, $includedFields);

        // Then
        $this->assertEquals(self::TEST_DATA, $includedData);
    }

    public function test_extractNestedData_producesSubsetOfArtifactWhenIncludedFieldsSet(): void
    {
        // Given
        $includedFields = ['name'];

        // When
        $includedData = ArrayHelper::extractNestedData(self::TEST_DATA, $includedFields);

        // Then
        $this->assertEquals(['name' => self::TEST_DATA['name']], $includedData);
    }

    public function test_extractNestedData_producesChildObjectWhenIndexReferencesObject(): void
    {
        // Given
        $includedFields = ['address'];

        // When
        $includedData = ArrayHelper::extractNestedData(self::TEST_DATA, $includedFields);

        // Then
        $this->assertEquals(['address' => self::TEST_DATA['address']], $includedData);
    }

    public function test_extractNestedData_producesArrayWithAllElementsWhenIndexReferencesArray(): void
    {
        // Given
        $includedFields = ['aliases'];

        // When
        $includedData = ArrayHelper::extractNestedData(self::TEST_DATA, $includedFields);

        // Then
        $this->assertEquals(['aliases' => self::TEST_DATA['aliases']], $includedData);
    }

    public function test_extractNestedData_producesArrayWithSpecifiedIndexKeysForEachElementWhenIndexUsesAsteriskSyntax(): void
    {
        // Given
        $data           = self::TEST_DATA;
        $includedFields = ['powers.*.name', 'powers.*.power'];

        // When
        $includedData = ArrayHelper::extractNestedData($data, $includedFields);

        // Then
        $this->assertEquals([
            'powers' => [
                ['name' => $data['powers'][0]['name'], 'power' => $data['powers'][0]['power']],
                ['name' => $data['powers'][1]['name'], 'power' => $data['powers'][1]['power']],
                ['name' => $data['powers'][2]['name'], 'power' => $data['powers'][2]['power']],
            ],
        ], $includedData);
    }

    public function test_extractNestedData_includeScalarInNestedArrayOfObjects(): void
    {
        // Given
        $data           = self::TEST_DATA;
        $includedFields = ['powers.*.specs.*.name'];

        // When
        $includedData = ArrayHelper::extractNestedData($data, $includedFields);

        // Then
        $this->assertEquals([
            'powers' => [
                [
                    'specs' => [
                        ['name' => $data['powers'][0]['specs'][0]['name']],
                        ['name' => $data['powers'][0]['specs'][1]['name']],
                    ],
                ],
                [
                    'specs' => [
                        ['name' => $data['powers'][1]['specs'][0]['name']],
                        ['name' => $data['powers'][1]['specs'][1]['name']],
                    ],
                ],
                [
                    'specs' => [
                        ['name' => $data['powers'][2]['specs'][0]['name']],
                        ['name' => $data['powers'][2]['specs'][1]['name']],
                    ],
                ],
            ],
        ], $includedData);
    }

    public function test_extractNestedData_includeMultipleScalarsInNestedArrayOfObjects(): void
    {
        // Given
        $data           = self::TEST_DATA;
        $includedFields = ['powers.*.specs.*.name', 'powers.*.specs.*.value'];

        // When
        $includedData = ArrayHelper::extractNestedData($data, $includedFields);

        // Then
        $this->assertEquals([
            'powers' => [
                [
                    'specs' => [
                        $data['powers'][0]['specs'][0],
                        $data['powers'][0]['specs'][1],
                    ],
                ],
                [
                    'specs' => [
                        $data['powers'][1]['specs'][0],
                        $data['powers'][1]['specs'][1],
                    ],
                ],
                [
                    'specs' => [
                        $data['powers'][2]['specs'][0],
                        $data['powers'][2]['specs'][1],
                    ],
                ],
            ],
        ], $includedData);
    }

    public function test_extractNestedData_nonExistingNestedFieldReturnsNull(): void
    {
        // Given
        $data           = self::TEST_DATA;
        $includedFields = ['powers.*.specs.*.nothing'];

        // When
        $includedData = ArrayHelper::extractNestedData($data, $includedFields);

        // Then
        $this->assertNull($includedData);
    }


    /*****************************
     * crossProductExtractData Tests
     *****************************/
    public function test_crossProductExtractData_producesEmptyArrayWhenNoFields(): void
    {
        // Given
        $data   = [];
        $fields = [];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData($data, $fields);

        // Then
        $this->assertEquals([], $crossProduct);
    }

    public function test_crossProductExtractData_producesSingleEntryForScalar(): void
    {
        // Given
        $fields = ['name'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData(self::TEST_DATA, $fields);

        // Then
        $this->assertEquals([['name' => 'Dan Newman']], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArray(): void
    {
        // Given
        $fields = ['aliases'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData(self::TEST_DATA, $fields);

        // Then
        $this->assertEquals([['aliases' => 'The Hammer'], ['aliases' => 'Daniel']], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArrayCrossScalar(): void
    {
        // Given
        $fields = ['aliases', 'name'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData(self::TEST_DATA, $fields);

        // Then
        $this->assertEquals([
            ['aliases' => 'The Hammer', 'name' => 'Dan Newman'],
            ['aliases' => 'Daniel', 'name' => 'Dan Newman'],
        ], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArrayCrossArray(): void
    {
        // Given
        $fields = ['aliases', 'weapons'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData(self::TEST_DATA, $fields);

        // Then
        $this->assertEquals([
            ['aliases' => 'The Hammer', 'weapons' => 'Sledge Hammer'],
            ['aliases' => 'Daniel', 'weapons' => 'Sledge Hammer'],
            ['aliases' => 'The Hammer', 'weapons' => 'Keyboard'],
            ['aliases' => 'Daniel', 'weapons' => 'Keyboard'],
        ], $crossProduct);
    }

    public function test_crossProductExtractData_producesMultipleEntriesForArrayOfObjectsCrossArray(): void
    {
        // Given
        $fields = ['aliases', 'powers.*.name'];

        // When
        $crossProduct = ArrayHelper::crossProductExtractData(self::TEST_DATA, $fields);

        // Then
        $this->assertEquals([
            ['aliases' => 'The Hammer', 'powers.*.name' => 'Hammer'],
            ['aliases' => 'Daniel', 'powers.*.name' => 'Hammer'],
            ['aliases' => 'The Hammer', 'powers.*.name' => 'Code'],
            ['aliases' => 'Daniel', 'powers.*.name' => 'Code'],
            ['aliases' => 'The Hammer', 'powers.*.name' => 'Slap'],
            ['aliases' => 'Daniel', 'powers.*.name' => 'Slap'],
        ], $crossProduct);
    }


    /*****************************
     * filterNestedData Tests
     *****************************/
    public function test_filterNestedData_filterExistingScalarReturnsOriginalData(): void
    {
        // Given
        $field = 'name';
        $value = 'Dan Newman';

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $this->assertEquals(self::TEST_DATA, $filteredData);
    }

    public function test_filterNestedData_filterNonExistingScalarReturnsNull(): void
    {
        // Given
        $field = 'name';
        $value = 'Mark';

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $this->assertNull($filteredData);
    }

    public function test_filterNestedData_filterExistingArrayReturnsDataWithFilteredArrayItems(): void
    {
        // Given
        $field = 'aliases';
        $value = 'The Hammer';

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $data            = self::TEST_DATA;
        $data['aliases'] = ['The Hammer'];
        $this->assertEquals($data, $filteredData);
    }

    public function test_filterNestedData_filterExistingObjectReturnsDataRegardlessOfAttributeOrder(): void
    {
        // Given
        $field = 'address';
        $value = [
            'zip'   => '80031',
            'city'  => 'Denver',
            'state' => 'CO',
        ];

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $this->assertEquals(self::TEST_DATA, $filteredData);
    }

    public function test_filterNestedData_filterExistingArrayOfObjectsByArrayRoot(): void
    {
        // Given
        $field = 'powers';
        $value = self::TEST_DATA['powers'][1];

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $data           = self::TEST_DATA;
        $data['powers'] = [self::TEST_DATA['powers'][1]];
        $this->assertEquals($data, $filteredData);
    }

    public function test_filterNestedData_filterExistingArrayOfObjectsReturnsDataWithFilteredArrayItems(): void
    {
        // Given
        $field = 'powers.*.name';
        $value = 'Code';

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $data           = self::TEST_DATA;
        $data['powers'] = [self::TEST_DATA['powers'][1]];
        $this->assertEquals($data, $filteredData);
    }

    public function test_filterNestedData_filterExistingNestedArrayInArrayOfObjectsReturnsDataWithFilteredArrayItems(): void
    {
        // Given
        $field = 'powers.*.uses';
        $value = 'compile';

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $data                      = self::TEST_DATA;
        $data['powers']            = [self::TEST_DATA['powers'][1]];
        $data['powers'][0]['uses'] = ['compile'];
        $this->assertEquals($data, $filteredData);
    }

    public function test_filterNestedData_filterExistingNestedArrayOfObjectsInArrayOfObjectsReturnsDataWithFilteredArrayItems(): void
    {
        // Given
        $field = 'powers.*.specs.*.value';
        $value = 30;

        // When
        $filteredData = ArrayHelper::filterNestedData(self::TEST_DATA, $field, $value);

        // Then
        $data                       = self::TEST_DATA;
        $data['powers']             = [self::TEST_DATA['powers'][0], self::TEST_DATA['powers'][2]];
        $data['powers'][0]['specs'] = [$data['powers'][0]['specs'][0]];
        $data['powers'][1]['specs'] = [$data['powers'][1]['specs'][0]];
        $this->assertEquals($data, $filteredData);
    }
}
