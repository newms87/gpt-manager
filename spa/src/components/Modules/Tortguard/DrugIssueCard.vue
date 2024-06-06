<template>
	<div class="drug-issue-card bg-sky-900 p-6 rounded-lg outline-2 outline-slate-500 outline shadow-md shadow-slate-500">
		<div class="flex items-stretch">
			<div>
				<LogoImage :src="drugIssue.drug.logo" :url="drugIssue.drug.url" />
				<div class="text-xl font-bold my-2">
					<a target="_blank" :href="drugIssue.drug.url">{{ drugIssue.drug.name }}</a>
				</div>
				<div class="text-lg"> {{ drugIssue.issue.name }}</div>
				<div class="my-2">{{ drugIssue.issue.description }}</div>
				<DrugIssueSeveritySection :issue="drugIssue.issue" />
			</div>
			<div class="ml-6">
				<div class="flex justify-end">
					<div class="bg-green-800 px-4 py-2 rounded-xl flex items-center">
						<div class="text-2xl font-bold">{{ drugIssue.issue.evaluation_score }}</div>
						<div class="text-lg">&nbsp;/ 100</div>
					</div>
				</div>

				<div class="text-right">
					<DrugIssueCompanySection :company="drugIssue.company" :get-source-url="getSourceUrl" />
					<DrugIssuePatentSection :get-sources="getSources" :drug="drugIssue.drug" :get-source-url="getSourceUrl" />
					<DrugIssueMarketSection :get-sources="getSources" :drug="drugIssue.drug" :get-source-url="getSourceUrl" />
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import DrugIssueCompanySection from "@/components/Modules/Tortguard/DrugIssueCompanySection";
import DrugIssueMarketSection from "@/components/Modules/Tortguard/DrugIssueMarketSection";
import DrugIssuePatentSection from "@/components/Modules/Tortguard/DrugIssuePatentSection";
import DrugIssueSeveritySection from "@/components/Modules/Tortguard/DrugIssueSeveritySection";
import { DrugIssue } from "@/components/Modules/Tortguard/drugs";
import LogoImage from "@/components/Shared/Images/LogoImage";

const props = defineProps<{ drugIssue: DrugIssue }>();

function getSources(table: string, field: string) {
	return props.drugIssue.data_sources.filter((source) => source.table === table && source.field === field);
}

function getSource(table: string, field: string) {
	return props.drugIssue.data_sources.find((source) => source.table === table && source.field === field);
}

function getSourceUrl(table: string, field: string) {
	return getSource(table, field)?.url;
}
</script>
