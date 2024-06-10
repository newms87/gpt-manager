<?php

use App\AiTools\GoogleSerpAiTool;
use App\AiTools\UrlToImageAiTool;
use App\Api\OpenAi\OpenAiApi;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'          => env('AI_SEED', 11181987),
    'default_model' => 'gpt-4o-2024-05-13',
    'models'        => [
        OpenAiApi::$serviceName => [
            'gpt-4o-2024-05-13'      => [
                'input'  => .005,
                'output' => .015,
                'image'  => [
                    'tokens' => 170,
                    'base'   => 85,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-4-turbo-2024-04-09' => [
                'input'  => .01,
                'output' => .03,
                'image'  => [
                    'tokens' => 170,
                    'base'   => 85,
                    'tile'   => '512x512',
                ],
            ],
            'gpt-3.5-turbo-0125'     => [
                'input'  => .0005,
                'output' => .0015,
            ],

            // These models are either too expensive / don't seem to work / limited capabilities
            //            'gpt-4'                  => [
            //                'input'  => .03,
            //                'output' => .06,
            //            ],
            //            'gpt-4-32k'              => [
            //                'input'  => .06,
            //                'output' => .12,
            //            ],
            //            'gpt-3.5-turbo-instruct' => [
            //                'input'  => .0015,
            //                'output' => .0020,
            //            ],
        ],
    ],
    'apis'          => [
        OpenAiApi::$serviceName => OpenAiApi::class,
    ],
    'tools'         => [
        [
            'class'       => UrlToImageAiTool::class,
            'name'        => UrlToImageAiTool::NAME,
            'description' => UrlToImageAiTool::DESCRIPTION,
            'parameters'  => UrlToImageAiTool::PARAMETERS,
        ],
        [
            'class'       => GoogleSerpAiTool::class,
            'name'        => GoogleSerpAiTool::NAME,
            'description' => GoogleSerpAiTool::DESCRIPTION,
            'parameters'  => GoogleSerpAiTool::PARAMETERS,
        ],
    ],
];
