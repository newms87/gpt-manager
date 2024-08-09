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
				<div v-if="drugInjury.workflowRun" class="mr-6 bg-slate-800 p-4 rounded-xl">
					<div class="flex items-center flex-nowrap text-base">
						<QSpinnerBall class="w-6 mr-2" />
						<div>Researching</div>
						<ElapsedTimePill
							:start="drugInjury.workflowRun.started_at"
							:end="drugInjury.workflowRun.completed_at"
							class="ml-2"
							timer-class="py-1 px-3 bg-slate-700 rounded-lg text-xs w-32 text-center"
						/>
					</div>
					<div
						class="px-4 py-1.5 rounded-lg mt-2 w-28 text-center"
						:class="WORKFLOW_STATUS.resolve(drugInjury.workflowRun.status).classPrimary"
					>
						{{ drugInjury.workflowRun.status }}
					</div>
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
import ElapsedTimePill from "@/components/Modules/Workflows/WorkflowRuns/ElapsedTimePill";
import { ShowHideButton } from "@/components/Shared";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { TortguardRoutes } from "@/routes/tortguardRoutes";
import { FlashMessages, storeObject } from "quasar-ui-danx";
import { onMounted } from "vue";

const props = defineProps<{ drugInjury: DrugInjury }>();
const isShowing = defineModel<boolean>();

onMounted(autoRefresh);

async function autoRefresh() {
	if (props.drugInjury?.workflowRun) {
		const response = await TortguardRoutes.drugInjury(props.drugInjury.id);

		if (!response.success) {
			return FlashMessages.error("Failed to refresh " + props.drugInjury.name);
		}

		storeObject(response.drugInjury);
		setTimeout(autoRefresh, 3000);
	}
}
</script>
