<template>
	<div class="relative h-full">
		<div v-if="!drugSideEffects.length" class="text-center text-gray-400 text-xl py-10">Currently there are no active
			research projects. Ask the AI below to find you some!
		</div>
		<div v-else class="p-8 overflow-y-auto h-full">
			<ListTransition class="pb-24">
				<DrugSideEffectCard
					v-for="drugSideEffect in activeDrugSideEffects"
					:key="drugSideEffect.id"
					:drug-side-effect="drugSideEffect"
					class="mb-6"
					@update:model-value="onShow(drugSideEffect, $event)"
				/>
			</ListTransition>
		</div>

		<AiSearchBar
			:drug-side-effect="activeDrugSideEffect"
			class="absolute bottom-0 left-0 w-full px-8 py-4"
			@refresh="loadDashboard"
		/>
	</div>
</template>
<script setup lang="ts">
import AiSearchBar from "@/components/Modules/AiSearch/AiSearchBar";
import DrugSideEffectCard from "@/components/Modules/Tortguard/DrugSideEffectCard";
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";
import { FlashMessages, ListTransition, request, storeObjects } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const drugSideEffects = ref<DrugSideEffect[]>([]);
const activeDrugSideEffect = ref<DrugSideEffect | null>(null);
const activeDrugSideEffects = computed(() => activeDrugSideEffect.value ? drugSideEffects.value.filter((di) => activeDrugSideEffect.value?.id === di.id) : drugSideEffects.value);

onMounted(loadDashboard);

async function loadDashboard() {
	const result: {
		error?: string,
		message?: string,
		drugSideEffects?: DrugSideEffect[]
	} = await request.get("tortguard/dashboard");
	if (!result) {
		return FlashMessages.error("Failed to load dashboard");
	}

	if (result.error) {
		return FlashMessages.error(result.message || result.error);
	}

	if (result.drugSideEffects) {
		drugSideEffects.value = storeObjects(result.drugSideEffects);
	}
}

function onShow(drugSideEffect: DrugSideEffect, isShowing: boolean) {
	if (isShowing) {
		activeDrugSideEffect.value = drugSideEffect;
	} else {
		activeDrugSideEffect.value = null;
	}
}
</script>
