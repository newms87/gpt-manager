<template>
	<div class="grid grid-cols-4 gap-4 mt-4">
		<LabelValueBlock
			label="Severity Level"
			:value="issue.severity_level"
		/>
		<LabelValueBlock
			label="Hospitalization"
			:value="fBoolean(issue.hospitalization)"
		/>
		<LabelValueBlock
			label="Surgical Procedure"
			:value="fBoolean(issue.surgical_procedure)"
		/>
		<LabelValueBlock
			label="Permanent Disability"
			:value="fBoolean(issue.permanent_disability)"
		/>
		<LabelValueBlock
			label="Death"
			:value="fBoolean(issue.death)"
		/>
		<LabelValueBlock
			label="Ongoing Care"
			:value="fBoolean(issue.ongoing_care)"
		/>
		<LabelValueBlock
			class="col-span-2 !max-w-64"
			label="Economic Damage"
			:value="fCurrencyNoCents(issue.economic_damage_min) + ' - ' + fCurrencyNoCents(issue.economic_damage_max)"
		/>
	</div>
</template>

<script setup lang="ts">
import { Issue } from "@/components/Modules/Tortguard/drugs";
import { fBoolean, fCurrencyNoCents, LabelValueBlock } from "quasar-ui-danx";

defineProps<{ issue: Issue }>();
</script>
