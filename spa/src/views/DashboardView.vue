<template>
	<div class="relative h-full">
		<template v-if="!drugInjuries.length">
			<div class="text-center text-gray-400 text-lg">No drug injuries found</div>
		</template>
		<div v-else class="p-8 overflow-y-auto h-full">
			<ListTransition>
				<DrugInjuryCard
					v-for="drugInjury in activeDrugInjuries"
					:key="drugInjury.id"
					:drug-injury="drugInjury"
					class="mb-8"
					@update:model-value="onShow(drugInjury, $event)"
				/>
			</ListTransition>
		</div>

		<div v-if="!activeDrugInjury" class="absolute bottom-0 left-0 w-full px-8 py-4 flex items-center flex-nowrap">
			<TextField
				v-model="searchText"
				class="large-white flex-grow rounded-full overflow-hidden"
				input-class="text-xl h-14 rounded-full"
				placeholder="AI Search..."
			>
				<template #prepend>
					<div class="bg-purple-500 p-3 rounded-full ml-2 hover:bg-purple-900 transition-all cursor-pointer">
						<SearchIcon class="w-4 text-yellow-200" />
					</div>
				</template>
			</TextField>
		</div>
	</div>
</template>
<script setup lang="ts">
import DrugInjuryCard from "@/components/Modules/Tortguard/DrugInjuryCard";
import { DrugInjury } from "@/components/Modules/Tortguard/drugs";
import { FaSolidWandSparkles as SearchIcon } from "danx-icon";
import { FlashMessages, ListTransition, request, TextField } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const drugInjuries = ref<DrugInjury[]>([]);
const activeDrugInjury = ref<DrugInjury | null>(null);
const activeDrugInjuries = computed(() => activeDrugInjury.value ? drugInjuries.value.filter((di) => activeDrugInjury.value?.id === di.id) : drugInjuries.value);
const searchText = ref("");

onMounted(async () => {
	const result = await request.get("tortguard/dashboard");
	if (!result) {
		return FlashMessages.error("Failed to load drug issues");
	}

	if (result.error) {
		return FlashMessages.error(result.message || result.error);
	}

	if (result.drugInjuries) {
		drugInjuries.value = result.drugInjuries;
	}
});

function onShow(drugInjury: DrugInjury, isShowing: boolean) {
	if (isShowing) {
		activeDrugInjury.value = drugInjury;
	} else {
		activeDrugInjury.value = null;
	}
}
</script>
