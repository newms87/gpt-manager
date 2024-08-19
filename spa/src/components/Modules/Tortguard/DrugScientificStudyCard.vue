<template>
	<div class="bg-slate-900 p-4 rounded-xl">
		<div class="text-lg font-bold">
			<a :href="study.url" target="_blank">{{ study.name }}</a>
		</div>
		<div class="text-sm mt-2">{{ study.description }}</div>
		<div class="flex items-stretch mt-4">
			<div class="grid grid-cols-6 gap-4">
				<TeamObjectAttributeBlock
					label="Authors"
					:attribute="study.authors"
				/>
				<TeamObjectAttributeBlock
					label="Publication Date"
					:attribute="study.publication_date"
				/>
				<TeamObjectAttributeBlock
					label="Journal Name"
					:attribute="study.journal_name"
				/>
				<TeamObjectAttributeBlock
					label="DOI"
					:attribute="study.doi"
				/>
				<TeamObjectAttributeBlock
					label="Objective"
					:attribute="study.objective"
				/>
				<TeamObjectAttributeBlock
					label="Design"
					:attribute="study.study_design"
				/>
				<TeamObjectAttributeBlock
					label="Methodology"
					:attribute="study.methodology"
				/>
				<TeamObjectAttributeBlock
					label="Population"
					:attribute="study.population"
				/>
				<TeamObjectAttributeBlock
					label="Sample Size"
					:attribute="study.sample_size"
				/>
				<TeamObjectAttributeBlock
					label="Interventions"
					:attribute="study.interventions"
				/>
				<TeamObjectAttributeBlock
					label="Control Group"
					:attribute="study.control_group"
				/>
				<TeamObjectAttributeBlock
					label="Mesured Outcomes"
					:attribute="study.outcomes"
				/>
				<TeamObjectAttributeBlock
					label="Results"
					:attribute="study.results"
				/>
				<TeamObjectAttributeBlock
					label="Statistical Analysis"
					:attribute="study.statistical_analysis"
				/>
				<TeamObjectAttributeBlock
					label="Adverse Events"
					:attribute="study.adverse_events"
				/>
				<TeamObjectAttributeBlock
					label="Conclusions"
					:attribute="study.conclusions"
				/>
				<TeamObjectAttributeBlock
					label="Limitations"
					:attribute="study.limitations"
				/>
				<TeamObjectAttributeBlock
					label="Funding Sources"
					:attribute="study.funding_sources"
				/>
				<TeamObjectAttributeBlock
					label="Conflicts of Interest"
					:attribute="study.conflicts_of_interest"
				/>
				<TeamObjectAttributeBlock
					label="Ethical Approval"
					:attribute="study.ethical_approval"
				/>
				<TeamObjectAttributeBlock
					label="Trial Registration"
					:attribute="study.trial_registration_number"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { ScientificStudy } from "@/components/Modules/Tortguard/tortguard";

defineProps<{
	study: ScientificStudy;
}>();
</script>
