<?php

namespace App\AiTools;

use App\Services\Database\DatabaseRecordMapper;
use Exception;
use Illuminate\Support\Facades\Log;

class WriteDatabaseAiTool implements AiToolContract
{
    const string NAME        = 'write-database';
    const string DESCRIPTION = 'Writes data to the database';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'writes' => [
                'type'        => 'array',
                'description' => 'The writes to the database (INCOMPLETE FUNCTION)',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'anyProperty' => [
                            'oneOf'       => [
                                ['type' => 'string'],
                                ['type' => 'number'],
                                ['type' => 'object'],
                                ['type' => 'array'],
                                ['type' => 'boolean'],
                                ['type' => 'null'],
                            ],
                            'description' => 'A value of any type for the attribute',
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function execute($params): AiToolResponse
    {
        $response = new AiToolResponse();

        $databaseWrites = $params['writes'] ?? [];
        if ($databaseWrites) {
            if (team()->schema_file) {
                $file = app_path(team()->schema_file);

                try {
                    (new DatabaseRecordMapper)
                        ->setSchema(team()->namespace, $file)
                        ->map($databaseWrites);
                } catch(Exception $exception) {
                    Log::error("Error writing to database: " . $exception->getMessage());
                }
            } else {
                Log::error("No schema file found for team " . team()->namespace);
            }
        }

        return $response;
    }
}
