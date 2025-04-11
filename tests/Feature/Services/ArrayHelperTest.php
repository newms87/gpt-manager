<?php

namespace Tests\Feature\Services;

use Newms87\Danx\Helpers\ArrayHelper;
use Tests\AuthenticatedTestCase;

class ArrayHelperTest extends AuthenticatedTestCase
{
    public function test_crossProduct_OneGroupCreatedForOneItem(): void
    {
        // Given
        $groups = [
            0 => [
                ['name' => 'John', 'age' => 30],
            ],
        ];

        // When
        $crossProduct = ArrayHelper::crossProduct($groups);

        // Then
        $this->assertEquals([[['name' => 'John', 'age' => 30]]], $crossProduct);
    }

    public function test_crossProduct_OneGroupCreatedForOneItemInEachGroup(): void
    {
        // Given
        $groups = [
            0 => [
                ['name' => 'John', 'age' => 30],
            ],
            1 => [
                ['name' => 'Dan', 'age' => 34],
            ],
        ];

        // When
        $crossProduct = ArrayHelper::crossProduct($groups);

        // Then
        $this->assertEquals([
            0 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Dan', 'age' => 34],
            ],
        ], $crossProduct);
    }

    public function test_crossProduct_TwoGroupsCreatedForTwoItemsInOneGroup(): void
    {
        // Given
        $groups = [
            0 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Dan', 'age' => 34],
            ],
        ];

        // When
        $crossProduct = ArrayHelper::crossProduct($groups);

        // Then
        $this->assertEquals([
            0 => [
                ['name' => 'John', 'age' => 30],
            ],
            1 => [
                ['name' => 'Dan', 'age' => 34],
            ],
        ], $crossProduct);
    }

    public function test_crossProduct_TwoGroupsCreatedForTwoItemsInOneGroupAndOneItemInAnother(): void
    {
        // Given
        $groups = [
            0 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Dan', 'age' => 34],
            ],
            1 => [
                ['name' => 'Alice', 'age' => 28],
            ],
        ];

        // When
        $crossProduct = ArrayHelper::crossProduct($groups);

        // Then
        $this->assertEquals([
            0 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Alice', 'age' => 28],
            ],
            1 => [
                ['name' => 'Dan', 'age' => 34],
                ['name' => 'Alice', 'age' => 28],
            ],
        ], $crossProduct);
    }

    public function test_crossProduct_fourGroupsCreatedForTwoItemsInEachGroup(): void
    {
        // Given
        $groups = [
            0 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Dan', 'age' => 34],
            ],
            1 => [
                ['name' => 'Alice', 'age' => 28],
                ['name' => 'Bob', 'age' => 32],
            ],
        ];

        // When
        $crossProduct = ArrayHelper::crossProduct($groups);

        // Then
        $this->assertEquals([
            0 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Alice', 'age' => 28],
            ],
            1 => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Bob', 'age' => 32],
            ],
            2 => [
                ['name' => 'Dan', 'age' => 34],
                ['name' => 'Alice', 'age' => 28],
            ],

            3 => [
                ['name' => 'Dan', 'age' => 34],
                ['name' => 'Bob', 'age' => 32],
            ],
        ], $crossProduct);
    }
}
