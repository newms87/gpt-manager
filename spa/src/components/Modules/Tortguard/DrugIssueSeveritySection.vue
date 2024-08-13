<template>
	<div class="grid grid-cols-6 gap-4">
		<LabelValueBlock
			label="Prevalence"
			:value="drugSideEffect.prevalence?.value"
		/>
		<LabelValueBlock
			label="Hospitalization Rate"
			:value="drugSideEffect.hospitalization_rate?.value"
		/>
		<LabelValueBlock
			label="Death"
			:value="fBoolean(drugSideEffect.death?.value)"
		/>
		<LabelValueBlock
			label="Surgical Procedure"
			:value="fBoolean(drugSideEffect.surgical_procedure?.value)"
		/>
		<LabelValueBlock
			class="col-span-2"
			label="Economic Damage"
			:value="drugSideEffect.economic_impact?.value"
		/>
		<LabelValueBlock
			label="Permanent Disability"
			:value="fBoolean(drugSideEffect.permanent_disability?.value)"
		/>
		<LabelValueBlock
			label="Ongoing Care"
			:value="fBoolean(drugSideEffect.ongoing_care?.value)"
		/>

		<template v-if="expanded">
			<LabelValueBlock
				label="Is Reversible"
				:value="fBoolean(drugSideEffect.is_reversible?.value)"
			/>
			<LabelValueBlock
				label="Recovery Time"
				:value="drugSideEffect.recovery_time?.value"
			/>
			<LabelValueBlock
				label="Disability Rate"
				:value="drugSideEffect.disability_rate?.value"
			/>
			<LabelValueBlock
				label="Hospital Duration"
				:value="drugSideEffect.hospital_duration?.value"
			/>
			<LabelValueBlock
				class="col-span-2"
				label="Common Disabilities"
				:value="drugSideEffect.common_disabilities?.value"
			/>
			<LabelValueBlock
				label="Mortality Rate"
				:value="drugSideEffect.mortality_rate?.value"
			/>
			<LabelValueBlock
				v-if="drugSideEffect.death_factors?.value"
				class="col-span-2"
				label="Death Factors"
				:value="drugSideEffect.death_factors?.value"
			/>
			<LabelValueBlock
				label="Onset"
				:value="drugSideEffect.onset?.value"
			/>
			<LabelValueBlock
				label="Duration"
				:value="drugSideEffect.duration?.value"
			/>
			<LabelValueBlock
				label="Acute/Chronic"
				:value="drugSideEffect.acute_or_chronic?.value"
			/>
			<LabelValueBlock
				label="Ongoing Care Type"
				class="col-span-2"
				:value="drugSideEffect.ongoing_care_type?.value"
			/>
			<LabelValueBlock
				label="Ongoing Care Duration"
				class="col-span-2"
				:value="drugSideEffect.ongoing_care_duration?.value"
			/>
			<LabelValueBlock
				class="col-span-2"
				label="Indirect Costs"
				:value="drugSideEffect.indirect_costs?.value"
			/>
			<LabelValueBlock
				class="col-span-2"
				label="Economic Long Term"
				:value="drugSideEffect.economic_long_term?.value"
			/>

			<template v-if="drugSideEffect.surgical_procedure?.value">
				<LabelValueBlock
					class="col-span-1"
					label="Surgical Procedure Rate %"
					:value="drugSideEffect.surgical_procedure_rate?.value"
				/>
				<LabelValueBlock
					class="col-span-3"
					label="Surgical Procedure"
					:value="fBoolean(drugSideEffect.surgical_procedure_description?.value)"
				/>
			</template>

			<LabelValueBlock
				:class="drugSideEffect.demographics?.value ? 'col-span-3' : ''"
				label="Demographics"
				:value="drugSideEffect.demographics?.value"
			/>

			<LabelValueBlock
				class="col-span-3"
				label="Quality of Life"
				:value="drugSideEffect.quality_of_life?.value"
			/>
			<LabelValueBlock
				class="col-span-3"
				label="Psychological Impact"
				:value="drugSideEffect.psychological_impact?.value"
			/>
			<LabelValueBlock
				class="col-span-3"
				label="Social Impact"
				:value="drugSideEffect.social_impact?.value"
			/>
		</template>
	</div>
</template>

<script setup lang="ts">
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";
import { fBoolean, LabelValueBlock } from "quasar-ui-danx";

defineProps<{ drugSideEffect: DrugSideEffect, expanded: boolean }>();
</script>
