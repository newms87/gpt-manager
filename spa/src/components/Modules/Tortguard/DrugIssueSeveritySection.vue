<template>
	<div class="grid grid-cols-4 gap-4">
		<LabelValueBlock
			label="Severity Level"
			:value="drugSideEffect.severity_level?.value"
		/>
		<LabelValueBlock
			label="Hospitalization"
			:value="fBoolean(drugSideEffect.hospitalization?.value)"
		/>
		<LabelValueBlock
			label="Surgical Procedure"
			:value="fBoolean(drugSideEffect.surgical_procedure?.value)"
		/>
		<LabelValueBlock
			label="Permanent Disability"
			:value="fBoolean(drugSideEffect.permanent_disability?.value)"
		/>
		<LabelValueBlock
			label="Death"
			:value="fBoolean(drugSideEffect.death?.value)"
		/>
		<LabelValueBlock
			label="Ongoing Care"
			:value="fBoolean(drugSideEffect.ongoing_care?.value)"
		/>
		<LabelValueBlock
			class="col-span-2 !max-w-64"
			label="Economic Damage"
			:value="fCurrencyNoCents(drugSideEffect.economic_damage_min?.value) + ' - ' + fCurrencyNoCents(drugSideEffect.economic_damage_max?.value)"
		/>
	</div>
</template>

<script setup lang="ts">
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";
import { fBoolean, fCurrencyNoCents, LabelValueBlock } from "quasar-ui-danx";

defineProps<{ drugSideEffect: DrugSideEffect }>();
</script>
