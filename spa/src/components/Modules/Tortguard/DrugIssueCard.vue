<template>
	<div class="drug-issue-card">
		<div class="text-xl font-bold">{{ drugIssue.drug.name }}: {{ drugIssue.issue.name }}</div>
		<div class="">{{ drugIssue.issue.description }}</div>
		<QBtn @click="toggleSection('company')">Company Info</QBtn>
		<div v-if="showSection.company">
			<h3>Company</h3>
			<p><strong>Name:</strong> {{ drugIssue.company.name }}</p>
			<p><strong>Annual Revenue:</strong> {{ drugIssue.company.annual_revenue }}</p>
			<!-- Add more company fields here -->
		</div>

		<button @click="toggleSection('drug')">Drug Info</button>
		<div v-if="showSection.drug">
			<h3>Drug</h3>
			<p><strong>Patient Usage:</strong> {{ drugIssue.drug.patient_usage }}</p>
			<!-- Add more drug fields here -->
		</div>

		<button @click="toggleSection('issue')">Issue Info</button>
		<div v-if="showSection.issue">
			<h3>Issue</h3>
			<p><strong>Evaluation Score:</strong> {{ drugIssue.issue.evaluation_score }}</p>
			<!-- Add more issue fields here -->
		</div>

		<button @click="toggleSection('studies')">Scientific Studies</button>
		<div v-if="showSection.studies">
			<h3>Scientific Studies</h3>
			<ul>
				<li v-for="study in drugIssue.scientific_studies" :key="study.id">
					<p><strong>Name:</strong> {{ study.name }}</p>
					<!-- Add more study fields here -->
				</li>
			</ul>
		</div>

		<button @click="toggleSection('warnings')">FDA Warnings</button>
		<div v-if="showSection.warnings">
			<h3>FDA Warnings</h3>
			<ul>
				<li v-for="warning in drugIssue.fda_warnings" :key="warning.id">
					<p><strong>Name:</strong> {{ warning.name }}</p>
					<!-- Add more warning fields here -->
				</li>
			</ul>
		</div>

		<button @click="toggleSection('sources')">Data Sources</button>
		<div v-if="showSection.sources">
			<h3>Data Sources</h3>
			<ul>
				<li v-for="source in drugIssue.data_sources" :key="source.id">
					<p><strong>Name:</strong> {{ source.name }}</p>
					<!-- Add more source fields here -->
				</li>
			</ul>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref } from "vue";

export interface Company {
	id: number;
	name: string;
	annual_revenue: number;
	operating_income: number;
	net_income: number;
	total_assets: number;
	total_equity: number;
}

export interface Drug {
	id: number;
	name: string;
	patient_usage: string;
	patent: string;
	market_share: number;
	generics: boolean;
	statute_of_limitations_tolling: string;
}

export interface Issue {
	id: number;
	name: string;
	description: string;
	evaluation_score: number;
	severity_level: string;
	hospitalization: boolean;
	surgical_procedure: boolean;
	permanent_disability: boolean;
	death: boolean;
	ongoing_care: boolean;
	economic_damage_min: number;
	economic_damage_max: number;
}

export interface ScientificStudy {
	id: number;
	name: string;
	description: string;
	quality_grade: string;
	injury: string;
	group_size: number;
}

export interface FDAWarning {
	id: number;
	name: string;
	description: string;
}

export interface DataSource {
	id: number;
	name: string;
	url: string;
	table: string;
	field: string;
	explanation: string;
}

export interface DrugIssue {
	company: Company;
	drug: Drug;
	issue: Issue;
	scientific_studies: ScientificStudy[];
	fda_warnings: FDAWarning[];
	data_sources: DataSource[];
}

const props = defineProps<{ drugIssue: DrugIssue }>();

const showSection = ref({
	company: false,
	drug: false,
	issue: false,
	studies: false,
	warnings: false,
	sources: false
});

function toggleSection(section: keyof typeof showSection) {
	showSection.value[section] = !showSection.value[section];
}
</script>

<style scoped>
.drug-issue-card {
	border: 1px solid #DDD;
	padding: 16px;
	border-radius: 8px;
	margin-bottom: 16px;
	transition: box-shadow 0.3s;
}

.drug-issue-card:hover {
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.drug-issue-card h2 {
	font-size: 1.5em;
	margin-bottom: 0.5em;
}

.drug-issue-card button {
	background-color: #007BFF;
	color: white;
	border: none;
	padding: 8px 16px;
	border-radius: 4px;
	cursor: pointer;
	margin-bottom: 8px;
}

.drug-issue-card button:hover {
	background-color: #0056B3;
}

.drug-issue-card div {
	margin-bottom: 16px;
}

.drug-issue-card h3 {
	margin-bottom: 0.5em;
}
</style>
