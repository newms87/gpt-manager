<?php

use App\Api\OpenAi\OpenAiApi;
use App\Api\PerplexityAi\PerplexityAiApi;

$million  = 1_000_000;
$thousand = 1_000;

return [
    // The seed to use for AI completions to ensure consistent results
    'seed'                         => env('AI_SEED', 11181987),
    'default_model'                => 'gpt-5-mini',

    // Map legacy model names to their valid replacements
    'model_aliases'                => [
        'gpt-4o-mini'  => 'gpt-5-nano',
        'gpt-4o'       => 'gpt-5-mini',
        'gpt-4-turbo'  => 'gpt-5',
        'gpt-4'        => 'gpt-5',
        'o1-preview'   => 'gpt-5-pro',
        'o1-mini'      => 'gpt-5-mini',
        'o3-mini'      => 'gpt-5-mini',
    ],

    // Data deduplication agent configuration
    'classification_deduplication' => [
        'agent_name' => 'Data Normalization Agent',
        'model'      => env('AI_CLASSIFICATION_DEDUP_MODEL', 'gpt-5'),
        'timeout'    => env('AI_CLASSIFICATION_DEDUP_TIMEOUT', 600),
    ],

    // Classification verification agent configuration
    'classification_verification'  => [
        'agent_name' => 'Classification Verification Agent',
        'model'      => env('AI_CLASSIFICATION_VERIFICATION_MODEL', 'gpt-5'),
        'timeout'    => env('AI_CLASSIFICATION_VERIFICATION_TIMEOUT', 300),
    ],

    // Artifact naming configuration
    'artifact_naming'              => [
        'model'                  => env('ARTIFACT_NAMING_MODEL', 'gpt-5-nano'),
        'timeout'                => env('ARTIFACT_NAMING_TIMEOUT', 120),
        'max_batch_size'         => env('ARTIFACT_NAMING_MAX_BATCH_SIZE', 20),
        'content_preview_length' => 500,
    ],

    // File organization schema description generation
    'file_organization_schema'     => [
        'model'   => env('AI_FILE_ORG_SCHEMA_MODEL', 'gpt-5-mini'),
        'timeout' => env('AI_FILE_ORG_SCHEMA_TIMEOUT', 30),
    ],

    'variable_extraction' => [
        'name'        => 'Variable Extraction Agent',
        'model'       => 'gpt-5-mini',
        'api_options' => [
            'reasoning' => [
                'effort' => 'medium',
            ],
        ],
        'timeout'     => 300,
    ],

    // Variable mapping suggestions (AI-powered schema fragment matching)
    'variable_mapping_suggestions' => [
        'model'       => env('AI_VARIABLE_MAPPING_MODEL', 'gpt-5-mini'),
        'timeout'     => env('AI_VARIABLE_MAPPING_TIMEOUT', 120),
        'api_options' => [
            'reasoning' => [
                'effort' => 'low',
            ],
        ],
    ],

    // Template collaboration (conversation agent)
    'template_collaboration' => [
        'model'       => env('AI_TEMPLATE_COLLABORATION_MODEL', 'gpt-5-nano'),
        'timeout'     => env('AI_TEMPLATE_COLLABORATION_TIMEOUT', 120),
        'api_options' => [
            'reasoning' => [
                'effort' => 'low',
            ],
        ],
    ],

    // Template planning (planning agent for complex requests)
    'template_planning' => [
        'timeout'        => env('AI_TEMPLATE_PLANNING_TIMEOUT', 300),
        'efforts'        => [
            'very_low' => [
                'model'       => 'gpt-5-nano',
                'api_options' => [
                    'reasoning' => ['effort' => 'low'],
                ],
            ],
            'low' => [
                'model'       => 'gpt-5-mini',
                'api_options' => [
                    'reasoning' => ['effort' => 'low'],
                ],
            ],
            'medium' => [
                'model'       => 'gpt-5-mini',
                'api_options' => [
                    'reasoning' => ['effort' => 'medium'],
                ],
            ],
            'high' => [
                'model'       => 'gpt-5.2',
                'api_options' => [
                    'reasoning' => ['effort' => 'medium'],
                ],
            ],
            'very_high' => [
                'model'       => 'gpt-5.2',
                'api_options' => [
                    'reasoning' => ['effort' => 'high'],
                ],
            ],
        ],
        'default_effort' => 'low',
    ],

    // Template building (HTML/CSS generation agent)
    'template_building' => [
        'timeout'        => env('AI_TEMPLATE_BUILDING_TIMEOUT', 300),
        'efforts'        => [
            'very_low' => [
                'model'       => 'gpt-5-nano',
                'api_options' => [
                    'reasoning' => ['effort' => 'low'],
                ],
            ],
            'low' => [
                'model'       => 'gpt-5',
                'api_options' => [
                    'reasoning' => ['effort' => 'low'],
                ],
            ],
            'medium' => [
                'model'       => 'gpt-5.2',
                'api_options' => [
                    'reasoning' => ['effort' => 'medium'],
                ],
            ],
            'high' => [
                'model'       => 'gpt-5.2-codex',
                'api_options' => [
                    'reasoning' => ['effort' => 'medium'],
                ],
            ],
            'very_high' => [
                'model'       => 'gpt-5.2-codex',
                'api_options' => [
                    'reasoning' => ['effort' => 'high'],
                ],
            ],
        ],
        'default_effort' => 'medium',
        'partial_edits'  => [
            // Threshold in bytes below which full replacement is always used
            'small_file_threshold' => env('AI_TEMPLATE_BUILDING_SMALL_FILE_THRESHOLD', 1000),
            // Whether to automatically dispatch a correction build on recoverable errors
            'auto_correct'         => env('AI_TEMPLATE_BUILDING_AUTO_CORRECT', false),
        ],
    ],

    // Artifact deduplication
    'artifact_deduplication' => [
        'model'   => env('AI_ARTIFACT_DEDUP_MODEL', 'gpt-5-mini'),
        'timeout' => env('AI_ARTIFACT_DEDUP_TIMEOUT', 300),
    ],

    'models' => [
        'gpt-5.2'                           => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5.2',
            'context'      => 400_000,
            'input'        => 1.75  / $million,
            'cached_input' => 0.175 / $million,
            'output'       => 14.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => true,
                'distillation'       => true,
                'predicted_outputs'  => true,
                'image_input'        => true,
                'audio_input'        => true,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 500_000,
                'requests_per_minute' => 500,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5.1'                           => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5.1',
            'context'      => 400_000,
            'input'        => 1.25  / $million,
            'cached_input' => 0.125 / $million,
            'output'       => 10.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => true,
                'distillation'       => true,
                'predicted_outputs'  => true,
                'image_input'        => true,
                'audio_input'        => true,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 500_000,
                'requests_per_minute' => 500,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5'                             => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5',
            'context'      => 400_000,
            'input'        => 1.25  / $million,
            'cached_input' => 0.125 / $million,
            'output'       => 10.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => true,
                'distillation'       => true,
                'predicted_outputs'  => true,
                'image_input'        => true,
                'audio_input'        => true,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 800_000,
                'requests_per_minute' => 900,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5-mini'                        => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5 Mini',
            'context'      => 400_000,
            'input'        => 0.25  / $million,
            'cached_input' => 0.025 / $million,
            'output'       => 2.00  / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => true,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => true,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 4_000_000,
                'requests_per_minute' => 900,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5-nano'                        => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5 Nano',
            'context'      => 400_000,
            'input'        => 0.05  / $million,
            'cached_input' => 0.005 / $million,
            'output'       => 0.40  / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => true,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => true,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 4_000_000,
                'requests_per_minute' => 900,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5.2-codex'                     => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5.2 Codex',
            'context'      => 400_000,
            'input'        => 1.75  / $million,
            'cached_input' => 0.175 / $million,
            'output'       => 14.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => false,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => false,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 500_000,
                'requests_per_minute' => 500,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5.1-codex-max'                 => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5.1 Codex Max',
            'context'      => 400_000,
            'input'        => 1.25  / $million,
            'cached_input' => 0.125 / $million,
            'output'       => 10.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => false,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => false,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 500_000,
                'requests_per_minute' => 500,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5.1-codex'                     => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5.1 Codex',
            'context'      => 400_000,
            'input'        => 1.25  / $million,
            'cached_input' => 0.125 / $million,
            'output'       => 10.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => false,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => false,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 500_000,
                'requests_per_minute' => 500,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5-codex'                       => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5 Codex',
            'context'      => 400_000,
            'input'        => 1.25  / $million,
            'cached_input' => 0.125 / $million,
            'output'       => 10.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => false,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => false,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 500_000,
                'requests_per_minute' => 500,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5.2-pro'                       => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5.2 Pro',
            'context'      => 400_000,
            'input'        => 21.00  / $million,
            'cached_input' => null,
            'output'       => 168.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => false,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => false,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 100_000,
                'requests_per_minute' => 200,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'gpt-5-pro'                         => [
            'api'          => OpenAiApi::class,
            'name'         => 'GPT 5 Pro',
            'context'      => 400_000,
            'input'        => 15.00  / $million,
            'cached_input' => null,
            'output'       => 120.00 / $million,
            'features'     => [
                'streaming'          => true,
                'function_calling'   => true,
                'structured_outputs' => true,
                'fine_tuning'        => false,
                'distillation'       => false,
                'predicted_outputs'  => false,
                'image_input'        => true,
                'audio_input'        => false,
                'reasoning'          => true,
                'temperature'        => false,
            ],
            'rate_limits'  => [
                'tokens_per_minute'   => 100_000,
                'requests_per_minute' => 200,
            ],
            'image'        => [
                'tokens' => 170,
                'base'   => 85,
                'tile'   => '512x512',
            ],
        ],
        'llama-3.1-sonar-small-128k-online' => [
            'api'         => PerplexityAiApi::class,
            'context'     => 127072,
            'input'       => .2 / $million,
            'output'      => .2 / $million,
            'per_request' => 5  / $thousand,
        ],
        'llama-3.1-sonar-large-128k-online' => [
            'api'         => PerplexityAiApi::class,
            'context'     => 127072,
            'input'       => 1 / $million,
            'output'      => 1 / $million,
            'per_request' => 5 / $thousand,
        ],
        'llama-3.1-sonar-huge-128k-online'  => [
            'api'         => PerplexityAiApi::class,
            'context'     => 127072,
            'input'       => 5 / $million,
            'output'      => 5 / $million,
            'per_request' => 5 / $thousand,
        ],
    ],
];
