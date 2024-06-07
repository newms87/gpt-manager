<?php

namespace App\Teams\TortGuard;

use App\Http\Controllers\Controller;
use App\Services\Database\SchemaManager;
use Illuminate\Database\Query\Builder;

class TortguardController extends Controller
{
    public function getDashboardData(): array
    {
        $schemaFile = app_path('Teams/TortGuard/schema.yaml');
        $schema     = new SchemaManager('tortguard', $schemaFile);
        $issues     = $schema->query('issues')->where('is_dashboard_approved', 1)->get()->toArray();

        $subjectIssues = [];

        foreach($issues as $issue) {
            $issue             = (array)$issue;
            $subject           = $schema->findRecord('subjects', $issue['subject_id']);
            $company           = $schema->findRecord('companies', $subject['company_id']);
            $scientificStudies = $schema->query('scientific_studies')
                ->where('subject_id', $issue['subject_id'])
                ->get()
                ->toArray();
            $warnings          = $schema->query('warnings')->where('subject_id', $issue['subject_id'])->get();
            $dataSources       = $schema->query('data_sources')->where(function (Builder $builder) use ($subject, $company, $issue) {
                $builder->orWhere(fn($b) => $b->where('table', 'companies')->where('record_id', $company['id']));
                $builder->orWhere(fn($b) => $b->where('table', 'subjects')->where('record_id', $subject['id']));
                $builder->orWhere(fn($b) => $b->where('table', 'issues')->where('record_id', $issue['id']));
            })->get();

            $subject['generics'] = json_decode($subject['generics'] ?? '', true);

            foreach($warnings as $index => $warning) {
                $warnings[$index]->injury_risks = json_decode($warning->injury_risks ?? '', true);
            }

            $subjectIssues[] = [
                'issue'              => $issue,
                'company'            => $company,
                'drug'               => $subject,
                'scientific_studies' => $scientificStudies,
                'warnings'           => $warnings,
                'data_sources'       => $dataSources,
            ];
        }

        return $subjectIssues;
    }
}
