<template>
	<div class="drug-issue-card bg-slate-800 rounded-lg outline-2 outline-slate-500 outline shadow-md shadow-slate-500 w-full">
		<div class="card-header flex items-center flex-nowrap bg-sky-900">
			<div class="flex items-center flex-grow flex-nowrap">
				<LogoImage
					v-if="drugSideEffect.product?.meta?.logo"
					:src="drugSideEffect.product.meta?.logo"
					:url="drugSideEffect.product.url"
					class="w-32"
				/>
				<div class="px-4 py-2 w-48 flex-shrink-0">
					<div>
						<a target="_blank" :href="drugSideEffect.product.url" class="text-xl font-bold text-sky-300">
							{{ drugSideEffect.product.name }}
						</a>
					</div>
					<div>
						{{ drugSideEffect.product.indications.map(i => i.name).join(", ") }}
					</div>
				</div>
				<ShowHideButton
					class="bg-lime-950 px-6 py-2 rounded-full"
					:label="sideEffectName"
					@click="onShow"
				/>
				<div v-if="drugSideEffect.description" class="ml-4">{{ drugSideEffect.description }}</div>
			</div>
			<div class="mx-3 my-2 flex items-center flex-nowrap">
				<WorkflowResearchingCard
					v-if="drugSideEffect.workflowRuns"
					:workflow-runs="drugSideEffect.workflowRuns"
					class="mr-6 bg-slate-800"
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
			<div v-if="isLoading || !drugSideEffect.product?.generics" class="m-4">
				<QSkeleton class="w-full h-[30rem]" />
			</div>

			<template v-else>
				<div class="flex items-start flex-nowrap mt-4 mx-4">
					<div v-if="drugSideEffect.product.companies" class="flex-shrink-0">
						<DrugCompanyCard
							v-for="company in drugSideEffect.product.companies"
							:key="company.id"
							:company="company"
							class="bg-slate-700 w-[30rem] mb-4"
						/>
					</div>
					<div class="flex-grow ml-4">
						<DrugMarketSection :product="drugSideEffect.product" class="mb-4" />
						<DrugSideEffectSeveritySection :drug-side-effect="drugSideEffect" />
					</div>
				</div>
				<div class="p-4">
					<DrugPatentSection :patents="drugSideEffect.product.patents || []" />
					<DrugScientificStudiesSection :studies="drugSideEffect.product.scientificStudies || []" class="mt-6" />
					<DrugWarningsSection :warnings="drugSideEffect.product.warnings || []" class="mt-6" />
				</div>
			</template>
		</div>
	</div>
</template>

<script setup lang="ts">
import DrugCompanyCard from "@/components/Modules/Tortguard/DrugCompanyCard";
import DrugMarketSection from "@/components/Modules/Tortguard/DrugMarketSection";
import DrugPatentSection from "@/components/Modules/Tortguard/DrugPatentSection";
import DrugScientificStudiesSection from "@/components/Modules/Tortguard/DrugScientificStudiesSection";
import DrugSideEffectSeveritySection from "@/components/Modules/Tortguard/DrugSideEffectSeveritySection";
import DrugWarningsSection from "@/components/Modules/Tortguard/DrugWarningsSection";
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/config/workflows";
import WorkflowResearchingCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowResearchCard";
import LogoImage from "@/components/Shared/Images/LogoImage";
import { TortguardRoutes } from "@/routes/tortguardRoutes";
import { autoRefreshObject, ShowHideButton, storeObject } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const props = defineProps<{ drugSideEffect: DrugSideEffect }>();
const isShowing = defineModel<boolean>();

onMounted(() => {
	autoRefreshObject(
		props.drugSideEffect,
		(d: DrugSideEffect) => !!d.workflowRuns.find(wr => wr.status === WORKFLOW_STATUS.RUNNING.value),
		(d: DrugSideEffect) => TortguardRoutes.drugSideEffect(d.id)
	);
});

const sideEffectName = computed(() => props.drugSideEffect.name.replace(props.drugSideEffect.product.name + ": ", ""));
function onShow() {
	isShowing.value = !isShowing.value;
	loadDrugSideEffect();
}

const isLoading = ref(false);
async function loadDrugSideEffect() {
	isLoading.value = true;
	const response = await TortguardRoutes.drugSideEffect(props.drugSideEffect.id);

	if (response) {
		storeObject(response);
	}
	isLoading.value = false;
}

</script>
