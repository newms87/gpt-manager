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
				<div class="flex flex-col gap-2">
					<SelectField
						v-model="selectedStatuses"
						multiple
						placeholder="(All Statuses)"
						:options="filterFieldOptions?.status || []"
						@update="filters = {...filters, status: selectedStatuses}"
					/>
					<SelectField
						v-model="selectedOperations"
						multiple
						placeholder="(All Operations)"
						:options="filterFieldOptions?.operation || []"
						@update="filters = {...filters, operation: selectedOperations}"
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
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import PopoverMenu from "@/components/Shared/Utilities/PopoverMenu";
import { FaSolidFilter as FilterIcon } from "danx-icon";
import { AnyObject, SelectField, ShowHideButton } from "quasar-ui-danx";
import { computed, defineModel, ref, watch } from "vue";

// Props
const props = defineProps<{
	taskRunId: number;
}>();

// Define reactive model
const filters = defineModel<AnyObject>({ default: {} });

const selectedStatuses = ref([]);
const selectedOperations = ref([]);
const filterFieldOptions = ref<{ operation?: string[], status?: string[] }>({});

// PopMenu state
const isShowing = ref(false);

// Load filter options from backend
async function loadFilterOptions() {
	if (!props.taskRunId) return;

	const options = await dxTaskProcess.routes.fieldOptions({
		params: { filter: { task_run_id: props.taskRunId } }
	});

	filterFieldOptions.value = options;
}

// Load filter options when the menu is shown
watch(isShowing, async (showing) => {
	if (showing && !filterFieldOptions.value.operation) {
		await loadFilterOptions();
	}
});

// Count active filters
const activeFilterCount = computed(() => Object.values(filters.value).filter((value) => value).length);

// Reset all filters
function resetFilters() {
	filters.value = {};
	selectedStatuses.value = [];
	selectedOperations.value = [];
	isShowing.value = false;
}
</script>
