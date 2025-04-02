<?php

return [
    'permissions' => [
        'view_imported_schemas'   => ['View Imported Schemas', 'Can view schemas that were imported from other teams in a resource package'],
        'edit_imported_schemas'   => ['Edit Imported Schemas', 'Can edit schemas that were imported from other teams in a resource package'],
        'view_imported_workflows' => ['View Imported Workflows', 'Can view workflows that were imported from other teams in a resource package'],
        'edit_imported_workflows' => ['Edit Imported Workflows', 'Can edit workflows that were imported from other teams in a resource package'],
        'export_workflows'        => ['Export Workflows', 'Can export workflows to a resource package'],
        'import_workflows'        => ['Import Workflows', 'Can import workflows from a resource package'],
    ],
    'roles'       => [
        'dev'                    => [
            'display'     => ['Developer', 'Developer Role'],
            'permissions' => [
                'view_imported_schemas',
                'edit_imported_schemas',
                'view_imported_workflows',
                'edit_imported_workflows',
                'export_workflows',
                'import_workflows',
            ],
        ],
        'prompt-engineer-tester' => [
            'display'     => ['Prompt Engineer Helper', 'Is allowed to view data related to prompt engineering for testing'],
            'permissions' => [
                'view_imported_schemas',
                'view_imported_workflows',
            ],
        ],
        'admin'                  => [
            'display'     => ['Admin', 'Manage all objects in the account and add users to teams'],
            'permissions' => [
                'export_workflows',
                'import_workflows',
            ],
        ],
        'editor'                 => [
            'display'     => ['Editor', 'Editor Role'],
            'permissions' => [],
        ],
        'viewer'                 => [
            'display'     => ['Viewer', 'Viewer Role'],
            'permissions' => [],
        ],
    ],
];
