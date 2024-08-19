<template>
	<div class="bg-slate-800 p-4 rounded-xl">
		<div class="text-lg font-bold">
			<a :href="warning.url" target="_blank">{{ warning.name }}</a>
		</div>
		<div class="text-sm mt-2">{{ warning.description }}</div>
		<div class="flex items-stretch mt-4">
			<div class="grid grid-cols-6 gap-4">
				<TeamObjectAttributeBlock
					label="Reference Number"
					:attribute="warning.reference_number"
				/>
				<TeamObjectAttributeBlock
					label="Warning Date"
					:attribute="warning.warning_date"
				/>
				<TeamObjectAttributeBlock
					label="Warning Type"
					:attribute="warning.warning_type"
				/>
				<TeamObjectAttributeBlock
					label="Reason"
					:attribute="warning.reason"
				/>
				<TeamObjectAttributeBlock
					label="Affected Populations"
					:attribute="warning.affected_populations"
				/>
				<TeamObjectAttributeBlock
					label="Recommended Actions"
					:attribute="warning.recommended_actions"
				/>
				<TeamObjectAttributeBlock
					label="Adverse Events"
					:attribute="warning.adverse_events"
				/>
				<TeamObjectAttributeBlock
					label="Risk Level"
					:attribute="warning.risk_level"
				/>
				<TeamObjectAttributeBlock
					label="Regulatory Actions"
					:attribute="warning.regulatory_actions"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { DrugWarning } from "@/components/Modules/Tortguard/tortguard";

defineProps<{
	warning: DrugWarning;
}>();
</script>
