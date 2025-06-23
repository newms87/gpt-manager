<template>
	<ShowHideButton
		v-model="isShowingTaskProcesses"
		:show-icon="ProcessListIcon"
		class="show-task-processes-button"
		:label="taskRun.process_count"
	>
		<InfoDialog
			v-if="isShowingTaskProcesses"
			:title="`${taskRun.taskDefinition.name} (${taskRun.id}): Task Processes`"
			hide-done
			@close="isShowingTaskProcesses = false"
		>
			<div class="w-[70rem] h-[80vh] overflow-hidden">
				<div class="flex flex-col flex-no-wrap h-full overflow-hidden">
					<div class="flex-x gap-2 mb-4">
						<TaskProcessFilterButton v-model="filters" />
						<SearchBox
							class="flex-grow"
							:model-value="filters.keywords"
							:debounce="500"
							@update:model-value="keywords => filters = {...filters, keywords}"
						/>
						<QCheckbox
							v-if="taskProcesses.length > 0"
							:model-value="isAllSelected"
							:indeterminate="isPartiallySelected"
							label="Select All"
							class="text-slate-300 whitespace-nowrap"
							@update:model-value="toggleSelectAll"
						/>
						<ActionButton
							v-if="selectedProcesses.length > 0"
							type="restart"
							:label="`Restart ${selectedProcesses.length}`"
							color="orange"
							size="sm"
							:confirm="true"
							:confirm-text="`Restart ${selectedProcesses.length} selected task processes? This will delete any existing output artifacts created by these processes.`"
							@click="batchRestart"
						/>
						<ActionButton type="refresh" size="sm" color="sky" tooltip="Refresh List" @click="loadTaskProcesses" />
					</div>
					<div class="flex-grow overflow-y-auto">
						<template v-if="taskProcesses.length === 0">
							<QSkeleton v-if="isLoading" class="h-12" />
							<div v-else class="text-center text-gray-500 font-bold h-12 flex items-center justify-center">
								There are no processes for this task
							</div>
						</template>
						<template v-else>
							<div
								v-for="taskProcess in taskProcesses"
								:key="taskProcess.id"
								class="bg-slate-700 p-2 mb-4 rounded-lg flex items-start gap-2"
							>
								<QCheckbox
									:model-value="selectedProcesses.includes(taskProcess.id)"
									class="mt-2"
									@update:model-value="value => toggleSelection(taskProcess.id, value)"
								/>
								<NodeTaskProcessCard
									:task-process="taskProcess"
									class="flex-grow"
									@restart="onRestart"
								/>
							</div>
						</template>
					</div>
					<PaginationNavigator v-model="pagination" class="bg-sky-950 px-4 py-1 rounded-lg mt-4 text-slate-400" />
				</div>
			</div>
		</InfoDialog>
	</ShowHideButton>
</template>

<script setup lang="ts">
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import TaskProcessFilterButton
	from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/TaskProcessFilterButton";
import NodeTaskProcessCard from "@/components/Modules/WorkflowCanvas/NodeTaskProcessCard";
import { PaginationNavigator, SearchBox } from "@/components/Shared";
import { usePusher } from "@/helpers/pusher";
import { TaskProcess, TaskRun } from "@/types";
import { PaginationModel } from "@/types/Pagination";
import { FaSolidFileInvoice as ProcessListIcon } from "danx-icon";
import { ActionButton, AnyObject, InfoDialog, ListControlsPagination, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, shallowRef, watch } from "vue";

const emit = defineEmits<{ restart: void }>();
const props = defineProps<{
	taskRun: TaskRun;
}>();

// Handle auto refreshing task processes while they're being shown
const isShowingTaskProcesses = ref(false);
const isLoading = ref(false);
const taskProcesses = shallowRef<TaskProcess[]>([]);
const selectedProcesses = ref<number[]>([]);

function onRestart() {
	emit("restart");
}

// Pagination state
const pagination = ref<PaginationModel>({
	page: 1,
	perPage: 10,
	total: 0
});

// Search text and filter state
const defaultFilters = {
	keywords: ""
};
const filters = ref<AnyObject>(defaultFilters);

// Watch for changes in pagination or filters to reload data
watch(filters, (value, oldValue) => {
	if (JSON.stringify(value) !== JSON.stringify(oldValue)) {
		pagination.value.page = 1;
		loadTaskProcesses();
	}
});
watch(pagination, loadTaskProcesses);

async function loadTaskProcesses() {
	isLoading.value = true;

	const results = await dxTaskProcess.routes.list({
		...pagination.value,
		filter: { ...filters.value, task_run_id: props.taskRun.id }
	} as ListControlsPagination);

	// Ignore bad responses (probably an abort or network connection issue)
	if (!results.data) return;

	taskProcesses.value = results.data as TaskProcess[];
	pagination.value.total = results.meta.total || 0;
	isLoading.value = false;
}

// Selection logic
const isAllSelected = computed(() =>
	taskProcesses.value.length > 0 && selectedProcesses.value.length === taskProcesses.value.length
);

const isPartiallySelected = computed(() =>
	selectedProcesses.value.length > 0 && selectedProcesses.value.length < taskProcesses.value.length
);

function toggleSelection(processId: number, selected: boolean) {
	if (selected) {
		selectedProcesses.value.push(processId);
	} else {
		selectedProcesses.value = selectedProcesses.value.filter(id => id !== processId);
	}
}

function toggleSelectAll(selected: boolean) {
	if (selected) {
		selectedProcesses.value = taskProcesses.value.map(p => p.id);
	} else {
		selectedProcesses.value = [];
	}
}

async function batchRestart() {
	const restartAction = dxTaskProcess.getAction("restart");

	for (const processId of selectedProcesses.value) {
		const process = taskProcesses.value.find(p => p.id === processId);
		if (process) {
			await restartAction.trigger(process);
		}
	}

	selectedProcesses.value = [];
	emit("restart");
	loadTaskProcesses();
}

// Toggle web socket subscription to task processes
watch(isShowingTaskProcesses, () => {
	if (isShowingTaskProcesses.value) {
		usePusher().subscribeToProcesses(props.taskRun);
		loadTaskProcesses();
	} else {
		usePusher().unsubscribeFromProcesses();
		filters.value = defaultFilters;
		selectedProcesses.value = [];
	}
});
</script>
