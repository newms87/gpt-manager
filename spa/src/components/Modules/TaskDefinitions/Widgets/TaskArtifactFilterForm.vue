<template>
	<div class="bg-sky-950 p-4 rounded">
		<div class="flex-x">
			<Component
				:is="TaskRunners.resolve(sourceTaskDefinition.task_runner_class).lottie"
				class="w-10 h-10"
				play-on-hover
			/>
			{{ sourceTaskDefinition.name }}
		</div>
		<div class="flex-x space-x-4">
			<div>
				<QCheckbox
					v-model="editableTaskArtifactFilter.include_text"
					label="Include Text?"
					class="text-slate-500"
					@update:model-value="onUpdate"
				/>
			</div>
			<div>
				<QCheckbox
					v-model="editableTaskArtifactFilter.include_files"
					label="Include Files?"
					class="text-slate-500"
					@update:model-value="onUpdate"
				/>
			</div>
			<div>
				<QCheckbox
					v-model="editableTaskArtifactFilter.include_json"
					label="Include Data?"
					class="text-slate-500"
					@update:model-value="onUpdate"
				/>
			</div>
			<LoadingSandLottie v-if="isSaving" class="w-12" />
		</div>
	</div>
</template>
<script setup lang="ts">
import LoadingSandLottie from "@/assets/dotlottie/LoadingSandLottie";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { dxTaskArtifactFilter } from "@/components/Modules/TaskDefinitions/TaskArtifactFilters/config";
import { TaskRunners } from "@/components/Modules/TaskDefinitions/TaskRunners";
import { TaskArtifactFilter, TaskDefinition } from "@/types";
import { ref } from "vue";

const props = defineProps<{
	sourceTaskDefinition: TaskDefinition;
	targetTaskDefinition: TaskDefinition;
	taskArtifactFilter?: TaskArtifactFilter;
}>();

const isSaving = ref(false);
const createArtifactFilter = dxTaskArtifactFilter.getAction("quick-create");
const updateArtifactFilter = dxTaskArtifactFilter.getAction("update");

const editableTaskArtifactFilter = ref<Partial<TaskArtifactFilter>>(props.taskArtifactFilter || {
	source_task_definition_id: props.sourceTaskDefinition.id,
	target_task_definition_id: props.targetTaskDefinition.id,
	include_text: true,
	include_files: true,
	include_json: true,
	fragment_selector: null
});

async function onUpdate(data: Partial<TaskArtifactFilter>) {
	isSaving.value = true;
	editableTaskArtifactFilter.value = { ...editableTaskArtifactFilter.value, ...data };

	if (!props.taskArtifactFilter) {
		await createArtifactFilter.trigger(null, editableTaskArtifactFilter.value);
		await dxTaskDefinition.routes.details(props.targetTaskDefinition, { taskArtifactFiltersAsTarget: true });
	} else {
		await updateArtifactFilter.trigger(props.taskArtifactFilter, editableTaskArtifactFilter.value);
	}
	isSaving.value = false;
}
</script>
