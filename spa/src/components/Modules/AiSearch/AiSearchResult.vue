<template>
	<div class="bg-slate-800 rounded-lg shadow-lg px-4 py-4 flex items-stretch flex-nowrap">
		<div class="w-64 flex-shrink-0">
			<div class="text-lg font-bold text-sky-300">
				<div class="mb-2 leading-4">
					<a :href="result.product_url" target="_blank">{{ result.product_name }}</a>
				</div>
				<div v-for="company in result.companies" :key="company.name" class="text-sm text-slate-400 ml-3 mt-2">
					{{ company.name }}
					<div v-if="company.parent_name" class="text-xs text-slate-500 ml-3 mt-1">
						Subsidiary of {{ company.parent_name }}
					</div>
				</div>
			</div>
		</div>
		<QSeparator vertical class="mx-4 bg-slate-300" />
		<div class="flex-grow ml-4 flex items-center">
			<div>
				<div class="max-w-[50rem]">{{ result.description }}</div>
				<div class="mt-4">
					<b>Generics:</b> {{ result.generic_drug_names.join(", ") }}
				</div>
			</div>
		</div>
		<div class="flex items-center flex-shrink-0">
			<div>
				<QBtn
					v-for="resultBySideEffect in resultsBySideEffect"
					:key="resultBySideEffect.side_effect"
					class="bg-lime-900 px-4 py-2 ml-3"
					:loading="isStartingResearch"
					@click="onResearch(resultBySideEffect)"
				>
					<ResearchIcon class="w-5 mr-3" />
					{{ resultBySideEffect.side_effect }}
				</QBtn>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { AiSearchRoutes } from "@/routes/searchRoutes";
import { ResearchResult, SearchResultItem, SearchResultItemBySideEffect } from "@/types/research";
import { FaSolidBook as ResearchIcon } from "danx-icon";
import { FlashMessages } from "quasar-ui-danx";
import { computed, ref } from "vue";

const emit = defineEmits(["research"]);
const props = defineProps<{ result: SearchResultItem }>();
const isStartingResearch = ref(false);
const resultsBySideEffect = computed<SearchResultItemBySideEffect[]>(() => props.result.side_effects.map(side_effect => ({
	...props.result,
	side_effects: undefined,
	side_effect
})));

async function onResearch(result: SearchResultItemBySideEffect) {
	isStartingResearch.value = true;
	const response: ResearchResult = await AiSearchRoutes.research(result);
	isStartingResearch.value = false;

	if (response.success) {
		emit("research", result);
		FlashMessages.info(`Researching ${result.product_name}: ${result.side_effect}. The following workflows have started running: ${response.workflowRunNames.join(", ")}`);
	} else {
		FlashMessages.error(response.message || "Failed to start researching agent. Try again later...");
	}
}
</script>
