<?php

namespace App\Teams\TortGuard;

use App\Http\Controllers\Controller;
use App\Services\Database\SchemaManager;
use Exception;
use Illuminate\Database\Query\Builder;

class TortguardController extends Controller
{
    /**
     * @return array
     * @throws Exception
     */
    public function getDashboardData(): array
    {
        $schema = new SchemaManager('tortguard', app_path('Teams/TortGuard/schema.yaml'));

        $issues = $schema->query('issues')->where('is_dashboard_approved', 1)->get();

        $subjectIssues = [];

        foreach($issues as $issue) {
            $subject           = $schema->query('subjects')->find($issue->subject_id);
            $company           = $schema->query('companies')->find($subject->company_id);
            $scientificStudies = $schema->query('scientific_studies')
                ->where('subject_id', $issue->subject_id)
                ->get()
                ->toArray();
            $warnings          = $schema->query('warnings')->where('subject_id', $issue->subject_id)->get();
            $dataSources       = $schema->query('data_sources')->where(function (Builder $builder) use ($subject, $company, $issue) {
                $builder->orWhere(fn($b) => $b->where('table', 'companies')->where('record_id', $company->id));
                $builder->orWhere(fn($b) => $b->where('table', 'subjects')->where('record_id', $subject->id));
                $builder->orWhere(fn($b) => $b->where('table', 'issues')->where('record_id', $issue->id));
            })->get();

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
