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
				<ShowHideButton
					class="bg-lime-950 px-6 py-2 rounded-full"
					:label="drugInjury.name"
					@click="isShowing = !isShowing"
				/>
				<div v-if="drugInjury.description" class="ml-4">{{ drugInjury.description }}</div>
			</div>
			<div class="mx-3 flex items-center flex-nowrap">
				<WorkflowResearchingCard
					v-if="drugInjury.workflowRun"
					:workflow-run="drugInjury.workflowRun"
					class="mr-6 bg-slate-800 my-2"
				/>
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
			<div class="flex items-start mt-4 mx-4">
				<div class="flex-grow">
					<div class="bg-slate-700 p-4 rounded-lg w-[30rem]">
						<h6 class="mb-2">{{ drugInjury.product.company.name }}</h6>
						<DrugIssueCompanySection :company="drugInjury.product.company" />
					</div>
				</div>
				<div class="bg-sky-950 p-4 rounded-lg">
					<h6 class="mb-2">Severity</h6>
					<DrugIssueSeveritySection class="w-[40rem]" :drug-injury="drugInjury" />
				</div>
			</div>
			<div class="p-4">
				<DrugIssuePatentSection :patents="drugInjury.product.patents || []" />
				<!--					<DrugIssueMarketSection :get-sources="getSources" :drug="drugInjury.drug" :get-source-url="getSourceUrl" />-->
				<DrugIssueScientificStudiesSection :studies="drugInjury.studies || []" class="mt-6" />
				<DrugIssueWarningsSection :warnings="drugInjury.warnings || []" class="mt-6" />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import DrugIssueCompanySection from "@/components/Modules/Tortguard/DrugIssueCompanySection";
import DrugIssuePatentSection from "@/components/Modules/Tortguard/DrugIssuePatentSection";
import DrugIssueScientificStudiesSection from "@/components/Modules/Tortguard/DrugIssueScientificStudiesSection";
import DrugIssueSeveritySection from "@/components/Modules/Tortguard/DrugIssueSeveritySection";
import DrugIssueWarningsSection from "@/components/Modules/Tortguard/DrugIssueWarningsSection";
import { DrugInjury } from "@/components/Modules/Tortguard/tortguard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import WorkflowResearchingCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowResearchCard";
import { ShowHideButton } from "@/components/Shared";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { TortguardRoutes } from "@/routes/tortguardRoutes";
import { autoRefreshObject } from "quasar-ui-danx";
import { onMounted } from "vue";

const props = defineProps<{ drugInjury: DrugInjury }>();
const isShowing = defineModel<boolean>();

onMounted(() => {
	autoRefreshObject(
		props.drugInjury,
		(d: DrugInjury) => d.workflowRun?.status === WORKFLOW_STATUS.RUNNING.value,
		(d: DrugInjury) => TortguardRoutes.drugInjury(d.id)
	);
});
</script>
