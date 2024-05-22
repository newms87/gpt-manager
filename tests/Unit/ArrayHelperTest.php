<?php

namespace Tests\Unit;

use Newms87\Danx\Helpers\ArrayHelper;
use Illuminate\Foundation\Testing\TestCase;

class ArrayHelperTest extends TestCase
{
    public function test_groupByDot()
    {
        // Given
        $array = [
            'service_dates' => [
                [
                    'date' => '2021-01-01',
                    'data' => 1,
                ],
                [
                    'date' => '2021-01-02',
                    'data' => 2,
                ],
            ],
            'other'         => 'stuff',
        ];

        // When
        $result = ArrayHelper::groupByDot($array, 'service_dates.date');

        // Then
        $this->assertEquals([
            '2021-01-01' => [
                'date' => '2021-01-01',
                'data' => 1,
            ],
            '2021-01-02' => [
                'date' => '2021-01-02',
                'data' => 2,
            ],
        ], $result);
    }

    public function test_groupByDot_handlesSingleLevelArray()
    {
        // Given
        $array = [
            'questions' => [
                "Question 1",
                "Question 2",
                "Question 3",
            ],
        ];

        // When
        $result = ArrayHelper::groupByDot($array, 'questions');

        // Then
        $this->assertEquals([
            "Question 1",
            "Question 2",
            "Question 3",
        ], $result);
    }
}
