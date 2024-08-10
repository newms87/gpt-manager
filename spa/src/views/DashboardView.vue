<template>
	<div class="relative h-full">
		<div v-if="!drugInjuries.length" class="text-center text-gray-400 text-lg">No drug injuries found</div>
		<div v-else class="p-8 overflow-y-auto h-full">
			<ListTransition class="pb-10">
				<DrugInjuryCard
					v-for="drugInjury in activeDrugInjuries"
					:key="drugInjury.id"
					:drug-injury="drugInjury"
					class="mb-6"
					@update:model-value="onShow(drugInjury, $event)"
				/>
			</ListTransition>
		</div>

		<AiSearchBar v-if="!activeDrugInjury" class="absolute bottom-0 left-0 w-full px-8 py-4" @refresh="loadDashboard" />
	</div>
</template>
<script setup lang="ts">
import AiSearchBar from "@/components/Modules/AiSearch/AiSearchBar";
import DrugInjuryCard from "@/components/Modules/Tortguard/DrugInjuryCard";
import { DrugInjury } from "@/components/Modules/Tortguard/tortguard";
import { FlashMessages, ListTransition, request, storeObjects } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const drugInjuries = ref<DrugInjury[]>([]);
const activeDrugInjury = ref<DrugInjury | null>(null);
const activeDrugInjuries = computed(() => activeDrugInjury.value ? drugInjuries.value.filter((di) => activeDrugInjury.value?.id === di.id) : drugInjuries.value);

onMounted(loadDashboard);

async function loadDashboard() {
	const result = await request.get("tortguard/dashboard");
	if (!result) {
		return FlashMessages.error("Failed to load dashboard");
	}

	if (result.error) {
		return FlashMessages.error(result.message || result.error);
	}

	if (result.drugInjuries) {
		drugInjuries.value = storeObjects(result.drugInjuries);
	}
}

function onShow(drugInjury: DrugInjury, isShowing: boolean) {
	if (isShowing) {
		activeDrugInjury.value = drugInjury;
	} else {
		activeDrugInjury.value = null;
	}
}
</script>
