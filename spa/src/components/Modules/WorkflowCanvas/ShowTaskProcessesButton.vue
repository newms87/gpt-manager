<template>
	<ShowHideButton
		v-model="isShowingTaskProcesses"
		:show-icon="ProcessListIcon"
		class="show-task-processes-button"
		:label="taskRun.process_count"
		@update:model-value="onShow"
	>
		<InfoDialog
			v-if="isShowingTaskProcesses"
			:title="`${taskRun.taskDefinition.name}: Task Processes`"
			hide-done
			@close="isShowingTaskProcesses = false"
		>
			<div class="w-[70rem] h-[80vh] overflow-hidden">
				<div class="flex flex-col flex-no-wrap h-full overflow-hidden">
					<div>
						<TaskProcessFilterButton v-model="filters" />
					</div>
					<div class="flex-grow overflow-y-auto">
						<template v-if="taskProcesses.length === 0">
							<QSkeleton v-if="isLoading" class="h-12" />
							<div v-else class="text-center text-gray-500 font-bold h-12 flex items-center justify-center">
								There are no processes for this task
							</div>
						</template>
						<template v-else>
							<NodeTaskProcessCard
								v-for="taskProcess in taskProcesses"
								:key="taskProcess.id"
								:task-process="taskProcess"
								class="bg-slate-700 p-2 my-2 rounded-lg"
								@restart="onRestart"
							/>
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
import { PaginationNavigator } from "@/components/Shared";
import { usePusher } from "@/helpers/pusher";
import { TaskProcess, TaskRun } from "@/types";
import { PaginationModel } from "@/types/Pagination";
import { FaSolidFileInvoice as ProcessListIcon } from "danx-icon";
import { AnyObject, InfoDialog, ListControlsPagination, ShowHideButton } from "quasar-ui-danx";
import { ref, shallowRef, watch } from "vue";

const emit = defineEmits<{ restart: void }>();
const props = defineProps<{
	taskRun: TaskRun;
}>();

// Handle auto refreshing task processes while they're being shown
const isShowingTaskProcesses = ref(false);
const isLoading = ref(false);
const taskProcesses = shallowRef<TaskProcess[]>([]);
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
const filters = ref<AnyObject>({
	keywords: ""
});

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

function onShow() {
	usePusher().subscribeToProcesses(props.taskRun);
	loadTaskProcesses();
}
</script>
