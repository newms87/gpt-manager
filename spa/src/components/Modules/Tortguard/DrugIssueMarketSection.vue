<template>
	<div class="drug-issue-market mt-6">
		<ShowHideButton
			v-if="drug.generics.length > 0"
			:label="'Market ' + drug.generic_name + ': ' + fShortNumber(drug.number_of_users) + ' patients'"
			class="bg-sky-950"
			@click="isShowing = !isShowing"
		/>
		<div v-else>No Generics (100% market share)</div>

		<template v-if="isShowing">
			<div class="grid grid-cols-2 gap-4 mt-4">
				<LabelValueBlock
					label="Generic Name"
					:value="drug.generic_name"
					:url="getSourceUrl('subjects', 'generic_name')"
				/>
				<LabelValueBlock
					label="Generics"
					:value="drug.generics.length"
					:url="getSourceUrl('subjects', 'generics')"
				/>
				<LabelValueBlock
					label="Market Share"
					:value="fPercent(drug.market_share)"
					:url="getSourceUrl('subjects', 'market_share')"
				/>
				<LabelValueBlock
					label="Patients"
					:value="fNumber(drug.number_of_users)"
					:url="getSourceUrl('subjects', 'number_of_users')"
				/>
			</div>
			<div class="max-w-[17rem] mt-5 text-justify">
				<div>
					{{ drug.generics.join(", ") }}
				</div>
				<DataSourceList :sources="getSources('subjects', 'generics')" class="mt-3" />
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import DataSourceList from "@/components/Modules/Tortguard/DataSourceList";
import { DataSource, Drug } from "@/components/Modules/Tortguard/drugs";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import { fNumber, fPercent, fShortNumber, LabelValueBlock } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{ drug: Drug, getSources: (table, field) => DataSource[], getSourceUrl: (table, field) => string }>();

const isShowing = ref(false);
</script>
