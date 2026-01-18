<template>
	<div class="flex flex-col flex-nowrap" :class="fillHeight ? 'h-full overflow-hidden' : ''">
		<div class="flex-x gap-2 mb-4">
			<TaskProcessFilterButton v-model="filters" :task-run-id="filterTaskRunId" />
			<SearchBox
				class="flex-grow"
				:model-value="filters.keywords"
				:debounce="500"
				@update:model-value="keywords => filters = {...filters, keywords}"
			/>
			<QCheckbox
				v-if="showBatchActions && taskProcesses.length > 0"
				:model-value="isAllSelected"
				:indeterminate="isPartiallySelected"
				label="Select All"
				class="text-slate-300 whitespace-nowrap"
				@update:model-value="toggleSelectAll"
			/>
			<ActionButton
				v-if="showBatchActions && selectedProcesses.length > 0"
				type="restart"
				:label="`Restart ${selectedProcesses.length}`"
				color="orange"
				size="sm"
				:confirm="true"
				:confirm-text="`Restart ${selectedProcesses.length} selected task processes? This will delete any existing output artifacts created by these processes.`"
				@click="batchRestart"
			/>
			<ActionButton
				type="refresh"
				size="sm"
				color="sky"
				tooltip="Refresh List"
				@click="loadTaskProcesses"
			/>
		</div>
		<div :class="fillHeight ? 'flex-grow overflow-y-auto' : ''">
			<template v-if="taskProcesses.length === 0">
				<QSkeleton v-if="isLoading" class="h-12" />
				<div
					v-else
					class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
				>
					There are no processes for this task
				</div>
			</template>
			<template v-else>
				<div
					v-for="taskProcess in taskProcesses"
					:key="taskProcess.id"
					class="bg-slate-700 p-2 mb-4 rounded-lg flex items-start gap-2 overflow-hidden"
				>
					<QCheckbox
						v-if="showBatchActions"
						:model-value="selectedProcesses.includes(taskProcess.id)"
						class="mt-2"
						@update:model-value="value => toggleSelection(taskProcess.id, value)"
					/>
					<NodeTaskProcessCard
						:task-process="taskProcess"
						class="flex-grow min-w-0 overflow-hidden"
						@restart="onProcessRestarted"
					/>
				</div>
			</template>
		</div>
		<PaginationNavigator
			v-model="pagination"
			class="bg-sky-950 px-4 py-1 rounded-lg mt-4 text-slate-400"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import TaskProcessFilterButton
	from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/TaskProcessFilterButton";
import NodeTaskProcessCard from "@/components/Modules/WorkflowCanvas/NodeTaskProcessCard";
import { PaginationNavigator, SearchBox } from "@/components/Shared";
import { usePusher } from "@/helpers/pusher";
import { TaskProcess } from "@/types";
import { PaginationModel } from "@/types/Pagination";
import { ActionButton, AnyObject, ListControlsPagination } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	taskRunId?: number;
	filter?: Record<string, any>;
	showBatchActions?: boolean;
	enableWebSocket?: boolean;
	perPage?: number;
	fillHeight?: boolean;
}>(), {
	taskRunId: undefined,
	filter: undefined,
	showBatchActions: true,
	enableWebSocket: true,
	perPage: 10,
	fillHeight: true
});

const emit = defineEmits<{
	(e: "process-restarted", oldId: number, newProcess?: TaskProcess): void;
}>();

const isLoading = ref(false);
const taskProcesses = ref<TaskProcess[]>([]);
const selectedProcesses = ref<number[]>([]);

// Compute the task run ID to use for filtering (from taskRunId prop or from filter prop)
const filterTaskRunId = computed(() => props.taskRunId || props.filter?.task_run_id);

// Pagination state
const pagination = ref<PaginationModel>({
	page: 1,
	perPage: props.perPage,
	total: 0
});

// Search text and filter state
const defaultFilters: AnyObject = {
	keywords: ""
};
const filters = ref<AnyObject>({ ...defaultFilters });

// Combine props.filter with local filters for the API request (excluding withTrashed which is a query param, not a filter)
const combinedFilter = computed(() => {
	const { withTrashed, ...filterWithoutTrashed } = props.filter || {};
	return {
		...filters.value,
		...filterWithoutTrashed,
		...(props.taskRunId ? { task_run_id: props.taskRunId } : {})
	};
});

// Watch for changes in pagination or filters to reload data
watch(filters, (value, oldValue) => {
	if (JSON.stringify(value) !== JSON.stringify(oldValue)) {
		pagination.value.page = 1;
		loadTaskProcesses();
	}
});
watch(pagination, loadTaskProcesses);

// Watch for prop changes that should trigger a reload
watch(() => [props.taskRunId, props.filter], () => {
	pagination.value.page = 1;
	loadTaskProcesses();
}, { deep: true });

async function loadTaskProcesses() {
	isLoading.value = true;

	const pager: ListControlsPagination = {
		...pagination.value,
		filter: combinedFilter.value
	};

	// Pass withTrashed as a query param option if specified
	const options = props.filter?.withTrashed ? { params: { withTrashed: true } } : undefined;

	const results = await dxTaskProcess.routes.list(pager, options);

	// Ignore bad responses (probably an abort or network connection issue)
	if (!results.data) return;

	taskProcesses.value = results.data as TaskProcess[];
	pagination.value.total = results.meta.total || 0;
	isLoading.value = false;
}

function onProcessRestarted(oldProcessId?: number, newProcess?: TaskProcess) {
	if (oldProcessId && newProcess) {
		const index = taskProcesses.value.findIndex(p => p.id === oldProcessId);
		if (index !== -1) {
			const updated = [...taskProcesses.value];
			updated[index] = newProcess;
			taskProcesses.value = updated;
		} else {
			loadTaskProcesses();
		}
		emit("process-restarted", oldProcessId, newProcess);
	}
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
	loadTaskProcesses();
}

// WebSocket subscription management
let isSubscribed = false;

async function subscribeToWebSocket() {
	if (!props.enableWebSocket || !filterTaskRunId.value || isSubscribed) return;

	const pusher = usePusher();
	if (pusher) {
		try {
			await pusher.subscribeToModel("TaskProcess", ["updated", "created"], { filter: { task_run_id: filterTaskRunId.value } });
			isSubscribed = true;
		} catch (error) {
			console.error("Failed to subscribe to task processes:", error);
		}
	}
}

async function unsubscribeFromWebSocket() {
	if (!isSubscribed || !filterTaskRunId.value) return;

	const pusher = usePusher();
	if (pusher) {
		try {
			await pusher.unsubscribeFromModel("TaskProcess", ["updated", "created"], { filter: { task_run_id: filterTaskRunId.value } });
			isSubscribed = false;
		} catch (error) {
			console.error("Failed to unsubscribe from task processes:", error);
		}
	}
}

// Lifecycle hooks
onMounted(() => {
	loadTaskProcesses();
	subscribeToWebSocket();
});

onUnmounted(() => {
	unsubscribeFromWebSocket();
});

// Expose methods for parent components
defineExpose({
	refresh: loadTaskProcesses
});
</script>
