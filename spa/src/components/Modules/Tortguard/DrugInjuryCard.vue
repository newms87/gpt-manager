<template>
	<div class="drug-issue-card bg-slate-800 rounded-lg outline-2 outline-slate-500 outline shadow-md shadow-slate-500 w-full">
		<div class="card-header flex items-center flex-nowrap bg-sky-900">
			<div class="flex items-center flex-grow flex-nowrap">
				<LogoImage
					v-if="drugInjury.product.meta?.logo"
					:src="drugInjury.product.meta?.logo"
					:url="drugInjury.product.url"
					class="w-32"
				/>
				<div class="px-4 py-2 w-48">
					<div>
						<a target="_blank" :href="drugInjury.product.url" class="text-xl font-bold text-sky-300">
							{{ drugInjury.product.name }}
						</a>
					</div>
					<div class="mt-2">
						<a target="_blank" :href="drugInjury.product.company.url" class="text-sm text-slate-300 font-semibold ">
							{{ drugInjury.product.company.name }}
						</a>
					</div>
				</div>
				<QBtn class="bg-lime-950 px-6 py-2 rounded-full" @click="isShowing = !isShowing">
					<div class="text-base"> {{ drugInjury.name }}</div>
					<div v-if="drugInjury.description" class="my-2">{{ drugInjury.description }}</div>
				</QBtn>
			</div>
			<div class="mx-3 flex items-center flex-nowrap">
				<div class="mr-4">
					<ShowHideButton v-model="isShowing" :label="'Details'" class="bg-slate-800 text-lg" />
				</div>
				<div
					class="px-4 py-2 rounded-xl flex items-center" :class="{
						'!bg-slate-900': !drugInjury.evaluation_score,
						'bg-green-800': drugInjury.evaluation_score?.value >= 80,
						'bg-yellow-600': drugInjury.evaluation_score?.value >= 50 && drugInjury.evaluation_score?.value < 80,
						'bg-red-800': drugInjury.evaluation_score?.value < 50

					}"
				>
					<div class="text-2xl font-bold">{{ drugInjury.evaluation_score?.value || "N/A" }}</div>
				</div>
			</div>
		</div>
		<div v-if="isShowing">
			<div class="flex items-start mt-4">
				<div class="flex-grow">
					<DrugIssueCompanySection class="w-72 bg-slate-800 p-4 rounded-lg" :company="drugInjury.product.company" />
				</div>
				<div class="bg-sky-950 p-4 rounded-lg">
					<h5>Severity</h5>
					<DrugIssueSeveritySection class="w-[40rem]" :drug-injury="drugInjury" />
				</div>
			</div>
			<div>
				<!--					<DrugIssuePatentSection :get-sources="getSources" :drug="drugInjury.drug" :get-source-url="getSourceUrl" />-->
				<!--					<DrugIssueMarketSection :get-sources="getSources" :drug="drugInjury.drug" :get-source-url="getSourceUrl" />-->
				<DrugIssueScientificStudiesSection :studies="drugInjury.studies || []" class="mt-6" />
				<DrugIssueWarningsSection :warnings="drugInjury.warnings || []" class="mt-6" />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import DrugIssueCompanySection from "@/components/Modules/Tortguard/DrugIssueCompanySection";
import DrugIssueScientificStudiesSection from "@/components/Modules/Tortguard/DrugIssueScientificStudiesSection";
import DrugIssueSeveritySection from "@/components/Modules/Tortguard/DrugIssueSeveritySection";
import DrugIssueWarningsSection from "@/components/Modules/Tortguard/DrugIssueWarningsSection";
import { DrugInjury } from "@/components/Modules/Tortguard/drugs";
import { ShowHideButton } from "@/components/Shared";
import LogoImage from "@/components/Shared/Images/LogoImage";

defineProps<{ drugInjury: DrugInjury }>();
const isShowing = defineModel<boolean>();
</script>
