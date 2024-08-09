<template>
	<div class="grid grid-cols-4 gap-4">
		<LabelValueBlock
			label="Severity Level"
			:value="drugInjury.severity_level?.value"
		/>
		<LabelValueBlock
			label="Hospitalization"
			:value="fBoolean(drugInjury.hospitalization?.value)"
		/>
		<LabelValueBlock
			label="Surgical Procedure"
			:value="fBoolean(drugInjury.surgical_procedure?.value)"
		/>
		<LabelValueBlock
			label="Permanent Disability"
			:value="fBoolean(drugInjury.permanent_disability?.value)"
		/>
		<LabelValueBlock
			label="Death"
			:value="fBoolean(drugInjury.death?.value)"
		/>
		<LabelValueBlock
			label="Ongoing Care"
			:value="fBoolean(drugInjury.ongoing_care?.value)"
		/>
		<LabelValueBlock
			class="col-span-2 !max-w-64"
			label="Economic Damage"
			:value="fCurrencyNoCents(drugInjury.economic_damage_min?.value) + ' - ' + fCurrencyNoCents(drugInjury.economic_damage_max?.value)"
		/>
	</div>
</template>

<script setup lang="ts">
import { DrugInjury } from "@/components/Modules/Tortguard/tortguard";
import { fBoolean, fCurrencyNoCents, LabelValueBlock } from "quasar-ui-danx";

defineProps<{ drugInjury: DrugInjury }>();
</script>
