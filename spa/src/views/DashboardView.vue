<template>
	<div class="flex items-center justify-center h-full text-4xl">
		<DrugIssueCard :drug-issue="drugIssue" />
	</div>
</template>
<script setup>
import DrugIssueCard from "@/components/Modules/Tortguard/DrugIssueCard";

const drugIssue = {
		"company": {
			"id": 1,
			"name": "PharmaCorp",
			"annual_revenue": 500000000,
			"operating_income": 100000000,
			"net_income": 75000000,
			"total_assets": 2000000000,
			"total_equity": 1000000000
		},
		"drug": {
			"id": 1,
			"name": "Viagra",
			"patient_usage": "Erectile dysfunction treatment",
			"patent": "US1234567",
			"market_share": 45.0,
			"generics": true,
			"statute_of_limitations_tolling": "5 years"
		},
		"issue": {
			"id": 1,
			"name": "Erection over 4+ hours",
			"description": "A condition where an erection lasts more than 4 hours, requiring medical attention.",
			"evaluation_score": 8,
			"severity_level": "High",
			"hospitalization": true,
			"surgical_procedure": false,
			"permanent_disability": false,
			"death": false,
			"ongoing_care": true,
			"economic_damage_min": 1000,
			"economic_damage_max": 10000
		},
		"scientific_studies": [
			{
				"id": 1,
				"name": "Study on long-term effects of Viagra",
				"description": "A study investigating the long-term effects of Viagra usage.",
				"quality_grade": "A",
				"injury": "None",
				"group_size": 500
			}
		],
		"fda_warnings": [
			{
				"id": 1,
				"name": "FDA Warning on Viagra",
				"description": "Warning issued by the FDA about potential side effects of Viagra."
			}
		],
		"data_sources": [
			{
				"id": 1,
				"name": "FDA Database",
				"url": "https://www.fda.gov/drugs/drug-safety-and-availability/drug-safety-communications",
				"table": "drugs",
				"field": "warnings",
				"explanation": "FDA warnings about drugs."
			}
		]
	}
;
</script>
