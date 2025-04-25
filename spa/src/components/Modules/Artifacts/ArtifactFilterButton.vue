<template>
	<div class="relative">
		<!-- Filter toggle button with counter badge -->
		<ShowHideButton
			v-model="isShowing"
			class="flex-shrink-0"
			:show-icon="FilterIcon"
			size="sm"
			tooltip="Toggle Filters"
			:label="activeFilterCount > 0 ? activeFilterCount : ''"
			:class="{'bg-slate-700': activeFilterCount === 0, 'bg-sky-600': activeFilterCount > 0}"
		/>

		<!-- Filter popup menu -->
		<Popover v-model="isShowing">
			<div class="bg-slate-700 p-4 rounded-lg shadow-xl">
				<h3 class="text-sm font-semibold mb-2">Show only artifacts with:</h3>

				<!-- Filter toggles -->
				<div class="flex-x gap-2">
					<!-- Text Content Filter -->
					<ShowHideButton
						v-model="hasTextContent"
						class="w-full justify-between"
						:class="hasTextContent ? 'bg-green-900 text-white' : 'bg-white text-gray-700 border border-gray-300'"
						:show-icon="TextIcon"
						:hide-icon="TextIcon"
					/>

					<!-- Files Filter -->
					<ShowHideButton
						v-model="hasFiles"
						class="w-full justify-between"
						:class="hasFiles ? 'bg-amber-900 text-white' : 'bg-white text-gray-700 border border-gray-300'"
						:show-icon="FilesIcon"
						:hide-icon="FilesIcon"
					/>

					<!-- JSON Content Filter -->
					<ShowHideButton
						v-model="hasJsonContent"
						class="w-full justify-between"
						:class="hasJsonContent ? 'bg-purple-700 text-white' : 'bg-white text-gray-700 border border-gray-300'"
						:show-icon="JsonIcon"
						:hide-icon="JsonIcon"
					/>

					<!-- Meta Filter -->
					<ShowHideButton
						v-model="hasMeta"
						class="w-full justify-between"
						:class="hasMeta ? 'bg-slate-500 text-white' : 'bg-white text-gray-700 border border-gray-300'"
						:show-icon="MetaIcon"
						:hide-icon="MetaIcon"
					/>
				</div>

				<!-- Reset filters button -->
				<div class="mt-4">
					<button
						class="w-full px-3 py-2 rounded-md text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 transition-colors"
						@click="resetFilters"
					>
						Reset All Filters
					</button>
				</div>
			</div>
		</Popover>
	</div>
</template>

<script setup lang="ts">
import Popover from "@/components/Shared/Utilities/PopoverMenu";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidFilter as FilterIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { AnyObject, ShowHideButton } from "quasar-ui-danx";
import { computed, defineModel, ref } from "vue";

// Define reactive model
const filters = defineModel<AnyObject>({ default: {} });

// PopMenu state
const isShowing = ref(false);
const hasTextContent = computed({
	get: () => !!filters.value.text_content,
	set: (value) => (filters.value.text_content = value ? { null: false } : undefined)
});
const hasJsonContent = computed({
	get: () => !!filters.value.json_content,
	set: (value) => (filters.value.json_content = value ? { null: false } : undefined)
});
const hasMeta = computed({
	get: () => !!filters.value.meta,
	set: (value) => (filters.value.meta = value ? { null: false } : undefined)
});
const hasFiles = computed({
	get: () => !!filters.value["storedFiles.id"],
	set: (value) => (filters.value["storedFiles.id"] = value ? { null: false } : undefined)
});

// Count active filters
const activeFilterCount = computed(() => Object.values(filters.value).filter((value) => value).length);

// Reset all filters
function resetFilters() {
	filters.value = {};
	isShowing.value = false;
}
</script>
