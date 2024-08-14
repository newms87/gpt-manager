<template>
	<div class="grid grid-cols-6 gap-4">
		<TeamObjectAttributeBlock
			label="Prevalence"
			:attribute="drugSideEffect.prevalence"
		/>
		<TeamObjectAttributeBlock
			label="Hospitalization Rate"
			:attribute="drugSideEffect.hospitalization_rate"
		/>
		<TeamObjectAttributeBlock
			label="Death"
			:attribute="drugSideEffect.death"
			format="boolean"
		/>
		<TeamObjectAttributeBlock
			label="Surgical Procedure"
			:attribute="drugSideEffect.surgical_procedure"
			format="boolean"
		/>
		<TeamObjectAttributeBlock
			label="Permanent Disability"
			:attribute="drugSideEffect.permanent_disability"
			format="boolean"
		/>
		<TeamObjectAttributeBlock
			label="Ongoing Care"
			:attribute="drugSideEffect.ongoing_care"
			format="boolean"
		/>

		<template v-if="expanded">
			<TeamObjectAttributeBlock
				label="Is Reversible"
				:attribute="drugSideEffect.is_reversible"
			/>
			<TeamObjectAttributeBlock
				label="Recovery Time"
				:attribute="drugSideEffect.recovery_time"
			/>
			<TeamObjectAttributeBlock
				label="Disability Rate"
				:attribute="drugSideEffect.disability_rate"
			/>
			<TeamObjectAttributeBlock
				label="Hospital Duration"
				:attribute="drugSideEffect.hospital_duration"
			/>
			<TeamObjectAttributeBlock
				class="col-span-2"
				label="Common Disabilities"
				:attribute="drugSideEffect.common_disabilities"
			/>
			<TeamObjectAttributeBlock
				label="Mortality Rate"
				:attribute="drugSideEffect.mortality_rate"
			/>
			<TeamObjectAttributeBlock
				v-if="drugSideEffect.death_factors"
				class="col-span-2"
				label="Death Factors"
				:attribute="drugSideEffect.death_factors"
			/>
			<TeamObjectAttributeBlock
				label="Onset"
				class="col-span-2"
				:attribute="drugSideEffect.onset"
			/>
			<TeamObjectAttributeBlock
				label="Duration"
				class="col-span-2"
				:attribute="drugSideEffect.duration"
			/>
			<TeamObjectAttributeBlock
				label="Acute/Chronic"
				:attribute="drugSideEffect.acute_or_chronic"
			/>
			<TeamObjectAttributeBlock
				label="Ongoing Care Type"
				class="col-span-2"
				:attribute="drugSideEffect.ongoing_care_type"
			/>
			<TeamObjectAttributeBlock
				label="Ongoing Care Duration"
				class="col-span-2"
				:attribute="drugSideEffect.ongoing_care_duration"
			/>
			<TeamObjectAttributeBlock
				class="col-span-2"
				label="Indirect Costs"
				:attribute="drugSideEffect.indirect_costs"
			/>
			<TeamObjectAttributeBlock
				class="col-span-2"
				label="Economic Long Term"
				:attribute="drugSideEffect.economic_long_term"
			/>
			<TeamObjectAttributeBlock
				class="col-span-4"
				label="Economic Damage"
				:attribute="drugSideEffect.economic_impact"
			/>
			<TeamObjectAttributeBlock
				:class="drugSideEffect.demographics ? 'col-span-3' : ''"
				label="Demographics"
				:attribute="drugSideEffect.demographics"
			/>
			<template v-if="drugSideEffect.surgical_procedure">
				<TeamObjectAttributeBlock
					class="col-span-1"
					label="Surgical Procedure Rate %"
					:attribute="drugSideEffect.surgical_procedure_rate"
				/>
				<TeamObjectAttributeBlock
					class="col-span-3"
					label="Surgical Procedure"
					:attribute="drugSideEffect.surgical_procedure_description"
				/>
			</template>
			<TeamObjectAttributeBlock
				class="col-span-3"
				label="Quality of Life"
				:attribute="drugSideEffect.quality_of_life"
			/>
			<TeamObjectAttributeBlock
				class="col-span-3"
				label="Psychological Impact"
				:attribute="drugSideEffect.psychological_impact"
			/>
			<TeamObjectAttributeBlock
				class="col-span-3"
				label="Social Impact"
				:attribute="drugSideEffect.social_impact"
			/>
		</template>
	</div>
</template>

<script setup lang="ts">
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";

defineProps<{ drugSideEffect: DrugSideEffect, expanded: boolean }>();
</script>
