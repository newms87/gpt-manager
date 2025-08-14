<?php

return [
    'workflows' => [
        'extract_data' => env('DEMAND_WORKFLOW_EXTRACT_DATA', 'Extract Service Dates'),
        'write_demand' => env('DEMAND_WORKFLOW_WRITE_DEMAND', 'Write Demand Summary'),
    ],
];