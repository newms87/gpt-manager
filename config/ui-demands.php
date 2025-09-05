<?php

return [
    'workflows' => [
        'schema_definition'        => env('DEMAND_WORKFLOW_SCHEMA_DEFINITION', 'Demand Schema'),
        'extract_data'             => env('DEMAND_WORKFLOW_EXTRACT_DATA', 'Extract Service Dates'),
        'write_medical_summary'    => env('DEMAND_WORKFLOW_WRITE_MEDICAL_SUMMARY', 'Write Medical Summary'),
        'write_demand_letter'      => env('DEMAND_WORKFLOW_WRITE_DEMAND_LETTER', 'Write Demand Letter'),
    ],
];
