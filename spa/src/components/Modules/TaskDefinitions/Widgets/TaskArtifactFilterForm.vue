<template>
	<div class="task-artifact-filter-form">
		<div class="flex-x">
			<Component
				:is="TaskRunners.resolve(sourceTaskDefinition.task_runner_class).lottie"
				class="w-10 h-10 mr-2"
				play-on-hover
			/>
			{{ sourceTaskDefinition.name }}
		</div>
		<div class="w-full mt-2 ml-4">

			<div>
				<SchemaEditorToolbox
					v-model="selectedSchemaDefinition"
					v-model:fragment="editableTaskArtifactFilter.schemaFragment"
					can-select
					hide-save-state
					editable
					can-select-fragment
					clearable
					:previewing="!!editableTaskArtifactFilter.schemaFragment"
					placeholder="Include All Data"
					@update:model-value="onUpdate"
					@update:fragment="onUpdate"
				>
					<template #header-start>
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
					</template>
				</SchemaEditorToolbox>
			</div>
			<LoadingSandLottie v-if="isSaving" class="w-12" />
		</div>
	</div>
</template>
<script setup lang="ts">
import LoadingSandLottie from "@/assets/dotlottie/LoadingSandLottie";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { loadSchemaDefinitions, schemaDefinitions } from "@/components/Modules/Schemas/SchemaDefinitions/store";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { dxTaskArtifactFilter } from "@/components/Modules/TaskDefinitions/TaskArtifactFilters/config";
import { TaskRunners } from "@/components/Modules/TaskDefinitions/TaskRunners";
import { SchemaDefinition, TaskArtifactFilter, TaskDefinition } from "@/types";
import { onMounted, ref, shallowRef } from "vue";

const props = defineProps<{
	sourceTaskDefinition: TaskDefinition;
	targetTaskDefinition: TaskDefinition;
	taskArtifactFilter?: TaskArtifactFilter;
}>();

const isSaving = ref(false);
const createArtifactFilter = dxTaskArtifactFilter.getAction("quick-create");
const updateArtifactFilter = dxTaskArtifactFilter.getAction("update");

const editableTaskArtifactFilter = ref<Partial<TaskArtifactFilter>>(props.taskArtifactFilter || {
	include_text: true,
	include_files: true,
	include_json: true,
	schemaFragment: null
});

const selectedSchemaDefinition = shallowRef<SchemaDefinition>(null);

onMounted(async () => {
	await loadSchemaDefinitions();
	if (props.taskArtifactFilter.schemaFragment) {
		selectedSchemaDefinition.value = schemaDefinitions.value.find(sd => sd.id === props.taskArtifactFilter.schemaFragment.schema_definition_id);
	}
});
async function onUpdate() {
	isSaving.value = true;

	const input = {
		include_text: editableTaskArtifactFilter.value.include_text,
		include_files: editableTaskArtifactFilter.value.include_files,
		include_json: editableTaskArtifactFilter.value.include_json,
		schema_fragment_id: (selectedSchemaDefinition.value && editableTaskArtifactFilter.value.schemaFragment?.id) || null
	};

	if (!props.taskArtifactFilter) {
		await createArtifactFilter.trigger(null, {
			source_task_definition_id: props.sourceTaskDefinition.id,
			target_task_definition_id: props.targetTaskDefinition.id,
			...input
		});
		await dxTaskDefinition.routes.details(props.targetTaskDefinition, { taskArtifactFiltersAsTarget: true });
	} else {
		await updateArtifactFilter.trigger(props.taskArtifactFilter, input);
	}
	isSaving.value = false;
}
</script>
