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
		<PopoverMenu v-model="isShowing">
			<div class="bg-slate-700 p-4 rounded-lg shadow-xl">
				<h3 class="text-sm font-semibold mb-2">Show only processes with:</h3>

				<!-- Filter toggles -->
				<div class="flex-x gap-2">
					<SelectField
						v-model="selectedStatuses"
						multiple
						placeholder="(All Statuses)"
						:options="statusOptions"
						@update="filters = {...filters, status: selectedStatuses}"
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
		</PopoverMenu>
	</div>
</template>

<script setup lang="ts">
import PopoverMenu from "@/components/Shared/Utilities/PopoverMenu";
import { FaSolidFilter as FilterIcon } from "danx-icon";
import { AnyObject, SelectField, ShowHideButton } from "quasar-ui-danx";
import { computed, defineModel, ref } from "vue";

// Define reactive model
const filters = defineModel<AnyObject>({ default: {} });

const selectedStatuses = ref([]);
const statusOptions = [
	{ label: "Running", value: "Running" },
	{ label: "Completed", value: "Completed" },
	{ label: "Failed", value: "Failed" },
	{ label: "Stopped", value: "Stopped" },
	{ label: "Pending", value: "Pending" },
	{ label: "Incomplete", value: "Incomplete" },
	{ label: "Timeout", value: "Timeout" }
];
// PopMenu state
const isShowing = ref(false);

// Count active filters
const activeFilterCount = computed(() => Object.values(filters.value).filter((value) => value).length);

// Reset all filters
function resetFilters() {
	filters.value = {};
	isShowing.value = false;
}
</script>
