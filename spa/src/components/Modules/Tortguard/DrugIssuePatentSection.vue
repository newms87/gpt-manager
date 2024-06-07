<template>
	<div class="drug-issue-patent mt-6">
		<ShowHideButton
			v-if="drug.patent_number"
			:label="'Patent ' + drug.patent_number"
			class="bg-sky-950"
			@click="isShowing = !isShowing"
		/>
		<div v-else>No Patent</div>

		<template v-if="isShowing">
			<div class="grid grid-cols-2 gap-4 mt-4">
				<LabelValueBlock
					label="Patent Number"
					:value="drug.patent_number"
					:url="getSourceUrl('subjects', 'patent_number')"
				/>
				<LabelValueBlock
					label="Filed"
					:value="fDate(drug.patent_filed_date)"
					:url="getSourceUrl('subjects', 'patent_filed_date')"
				/>
				<LabelValueBlock
					label="Expiration"
					:value="fDate(drug.patent_expiration_date)"
					:url="getSourceUrl('subjects', 'patent_expiration_date')"
				/>
				<LabelValueBlock
					label="Issued"
					:value="fDate(drug.patent_issued_date)"
					:url="getSourceUrl('subjects', 'patent_issued_date')"
				/>
			</div>
			<div class="max-w-[17rem] mt-5 text-justify">
				<div>{{ drug.patent_details }}</div>
				<DataSourceList :sources="getSources('subjects', 'patent_details')" class="mt-3" />
			</div>
		</template>
	</div>
</template>

<script setup lang="ts">
import DataSourceList from "@/components/Modules/Tortguard/DataSourceList";
import { DataSource, Drug } from "@/components/Modules/Tortguard/drugs";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import { fDate, LabelValueBlock } from "quasar-ui-danx";
import { ref } from "vue";

const isShowing = ref(false);
defineProps<{ drug: Drug, getSources: (table, field) => DataSource[], getSourceUrl: (table, field) => string }>();
</script>
