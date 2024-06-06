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
			</div>
			<div class="ml-6">
				<div class="flex justify-end">
					<div class="bg-green-800 px-4 py-2 rounded-xl flex items-center">
						<div class="text-2xl font-bold">{{ drugIssue.issue.evaluation_score }}</div>
						<div class="text-lg">&nbsp;/ 100</div>
					</div>
				</div>

				<div class="text-right">
					<div class="drug-issue-company mt-6">
						<div class="flex justify-end">
							<LogoImage :src="drugIssue.company.logo" class="max-w-24 max-h-10" :url="drugIssue.company.url" />
						</div>
						<div class="grid grid-cols-2 gap-4 mt-4">
							<LabelValueBlock label="Company" :value="drugIssue.company.name" :url="drugIssue.company.url" />
							<LabelValueBlock
								label="Annual Revenue"
								:value="fCurrency(drugIssue.company.annual_revenue)"
								:url="getSourceUrl('companies', 'annual_revenue')"
							/>
							<LabelValueBlock
								label="Operating Income"
								:value="fCurrency(drugIssue.company.operating_income)"
								:url="getSourceUrl('companies', 'operating_income')"
							/>
							<LabelValueBlock
								label="Net Income"
								:value="fCurrency(drugIssue.company.net_income)"
								:url="getSourceUrl('companies', 'net_income')"
							/>
							<LabelValueBlock
								label="Total Assets"
								:value="fCurrency(drugIssue.company.total_assets)"
								:url="getSourceUrl('companies', 'total_assets')"
							/>
							<LabelValueBlock
								label="Total Equity"
								:value="fCurrency(drugIssue.company.total_equity)"
								:url="getSourceUrl('companies', 'total_equity')"
							/>
						</div>
					</div>
					<div class="drug-issue-patent mt-6">
						<ShowHideButton
							v-if="drugIssue.drug.patent_number"
							:label="'Patent ' + drugIssue.drug.patent_number"
							class="bg-sky-950"
							@click="showSection.patent = !showSection.patent"
						/>
						<div v-else>No Patent</div>

						<template v-if="showSection.patent">
							<div class="grid grid-cols-2 gap-4 mt-4">
								<LabelValueBlock
									label="Patent Number"
									:value="drugIssue.drug.patent_number"
									:url="getSourceUrl('drugs', 'patent_number')"
								/>
								<LabelValueBlock
									label="Filed"
									:value="fDate(drugIssue.drug.patent_filed_date)"
									:url="getSourceUrl('drugs', 'patent_filed_date')"
								/>
								<LabelValueBlock
									label="Expiration"
									:value="fDate(drugIssue.drug.patent_expiration_date)"
									:url="getSourceUrl('drugs', 'patent_expiration_date')"
								/>
								<LabelValueBlock
									label="Issued"
									:value="fDate(drugIssue.drug.patent_issued_date)"
									:url="getSourceUrl('drugs', 'patent_issued_date')"
								/>
							</div>
							<div class="max-w-[17rem] mt-5 text-justify">
								{{ drugIssue.drug.patent_details }}
								<DataSourceList :sources="getSources('drugs', 'patent_details')" />
							</div>
						</template>
					</div>
					<div class="drug-issue-generics mt-6">
						<ShowHideButton
							v-if="drugIssue.drug.generics.length > 0"
							:label="'Generic ' + drugIssue.drug.generic_name + ': ' + drugIssue.drug.generics.length"
							class="bg-sky-950"
							@click="showSection.generics = !showSection.generics"
						/>
						<div v-else>No Generics (100% market share)</div>

						<template v-if="showSection.generics">
							<div class="grid grid-cols-2 gap-4 mt-4">
								<LabelValueBlock
									label="Generic Name"
									:value="drugIssue.drug.generic_name"
									:url="getSourceUrl('drugs', 'generic_name')"
								/>
								<LabelValueBlock
									label="Market Share"
									:value="fPercent(drugIssue.drug.market_share)"
									:url="getSourceUrl('drugs', 'market_share')"
								/>
							</div>
							<div class="max-w-[17rem] mt-5 text-justify">
								{{ drugIssue.drug.generics.join(", ") }}
								<DataSourceList :sources="getSources('drugs', 'generics')" />
							</div>
						</template>
					</div>
				</div>
			</div>
		</div>
		<div>

		</div>
	</div>
</template>

<script setup lang="ts">
import DataSourceList from "@/components/Modules/Tortguard/DataSourceList";
import { DrugIssue } from "@/components/Modules/Tortguard/drugs";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { fCurrency, fDate, fPercent, LabelValueBlock } from "quasar-ui-danx";
import { reactive } from "vue";

const props = defineProps<{ drugIssue: DrugIssue }>();

const showSection = reactive({
	company: false,
	patent: false,
	generics: false,
	studies: false,
	warnings: false
});

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

<style scoped lang="scss">
.grid {
	& > * {
		@apply max-w-32;
	}
}
</style>
