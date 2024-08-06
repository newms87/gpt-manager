<?php

namespace App\Console\Commands;

use App\Models\Team\Team;
use App\Services\Database\DatabaseSchemaMapper;
use Illuminate\Console\Command;

class TeamObjects extends Command
{
    protected $signature   = 'team:objects {team}';
    protected $description = 'Install the objects relationships schema for the team';

    public function handle()
    {
        $teamName = $this->argument('team');

        $team = Team::firstWhere('name', $teamName);

        if (!$team) {
            $this->error("Team $teamName not found");

            return;
        }

        $objectsSchemaFile = database_path('schemas/object_relationships.yaml');
        $mapper            = new DatabaseSchemaMapper;
        $mapper->map($team->namespace, $objectsSchemaFile);

        $this->info("Objects schema installed for $teamName in namespace $team->namespace");
    }
}
