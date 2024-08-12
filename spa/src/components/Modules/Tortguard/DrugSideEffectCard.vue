<template>
	<div class="drug-issue-card bg-slate-800 rounded-lg outline-2 outline-slate-500 outline shadow-md shadow-slate-500 w-full">
		<div class="card-header flex items-center flex-nowrap bg-sky-900">
			<div class="flex items-center flex-grow flex-nowrap">
				<LogoImage
					v-if="drugSideEffect.product.meta?.logo"
					:src="drugSideEffect.product.meta?.logo"
					:url="drugSideEffect.product.url"
					class="w-32"
				/>
				<div class="px-4 py-2 w-48">
					<div>
						<a target="_blank" :href="drugSideEffect.product.url" class="text-xl font-bold text-sky-300">
							{{ drugSideEffect.product.name }}
						</a>
					</div>
					<div class="mt-2">
						<a target="_blank" :href="drugSideEffect.product.company.url" class="text-sm text-slate-300 font-semibold ">
							{{ drugSideEffect.product.company.name }}
						</a>
					</div>
				</div>
				<ShowHideButton
					class="bg-lime-950 px-6 py-2 rounded-full"
					:label="drugSideEffect.name"
					@click="isShowing = !isShowing"
				/>
				<div v-if="drugSideEffect.description" class="ml-4">{{ drugSideEffect.description }}</div>
			</div>
			<div class="mx-3 flex items-center flex-nowrap">
				<WorkflowResearchingCard
					v-if="drugSideEffect.workflowRun"
					:workflow-run="drugSideEffect.workflowRun"
					class="mr-6 bg-slate-800 my-2"
				/>
				<div
					class="px-4 py-2 rounded-xl flex items-center" :class="{
						'!bg-slate-900': !drugSideEffect.evaluation_score,
						'bg-green-800': drugSideEffect.evaluation_score?.value >= 80,
						'bg-yellow-600': drugSideEffect.evaluation_score?.value >= 50 && drugSideEffect.evaluation_score?.value < 80,
						'bg-red-800': drugSideEffect.evaluation_score?.value < 50

					}"
				>
					<div class="text-2xl font-bold">{{ drugSideEffect.evaluation_score?.value || "N/A" }}</div>
				</div>
			</div>
		</div>
		<div v-if="isShowing">
			<div class="flex items-start mt-4 mx-4">
				<div class="flex-grow">
					<div class="bg-slate-700 p-4 rounded-lg w-[30rem]">
						<h6 class="mb-2">{{ drugSideEffect.product.company.name }}</h6>
						<DrugIssueCompanySection :company="drugSideEffect.product.company" />
					</div>
				</div>
				<div class="bg-sky-950 p-4 rounded-lg">
					<h6 class="mb-2">Severity</h6>
					<DrugIssueSeveritySection class="w-[40rem]" :drug-side-effect="drugSideEffect" />
				</div>
			</div>
			<div class="p-4">
				<DrugIssuePatentSection :patents="drugSideEffect.product.patents || []" />
				<!--					<DrugIssueMarketSection :get-sources="getSources" :drug="drugSideEffect.drug" :get-source-url="getSourceUrl" />-->
				<DrugIssueScientificStudiesSection :studies="drugSideEffect.studies || []" class="mt-6" />
				<DrugIssueWarningsSection :warnings="drugSideEffect.warnings || []" class="mt-6" />
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
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import WorkflowResearchingCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowResearchCard";
import { ShowHideButton } from "@/components/Shared";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { TortguardRoutes } from "@/routes/tortguardRoutes";
import { autoRefreshObject } from "quasar-ui-danx";
import { onMounted } from "vue";

const props = defineProps<{ drugSideEffect: DrugSideEffect }>();
const isShowing = defineModel<boolean>();

onMounted(() => {
	autoRefreshObject(
		props.drugSideEffect,
		(d: DrugSideEffect) => d.workflowRun?.status === WORKFLOW_STATUS.RUNNING.value,
		(d: DrugSideEffect) => TortguardRoutes.drugSideEffect(d.id)
	);
});
</script>
