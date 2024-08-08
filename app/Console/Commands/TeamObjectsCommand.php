<?php

namespace App\Console\Commands;

use App\Services\Database\DatabaseSchemaMapper;
use Illuminate\Console\Command;

class TeamObjectsCommand extends Command
{
    protected $signature   = 'team:objects {namespace} {--database=}';
    protected $description = 'Install the objects relationships schema in the given namespace';

    public function handle()
    {
        $namespace = $this->argument('namespace');
        $database  = $this->option('database');

        if ($database) {
            config(['database.connections.mysql.database' => $database]);
        }

        $objectsSchemaFile = database_path('schemas/object_relationships.yaml');
        $mapper            = new DatabaseSchemaMapper;
        $mapper->map($namespace, $objectsSchemaFile);

        $this->info("Objects schema installed in namespace $namespace");
    }
}
