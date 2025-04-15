<template>
	<div class="task-artifact-filter-form">
		<div class="flex-x">
			<Component
				:is="TaskRunnerClasses.resolve(sourceTaskDefinition.task_runner_name).lottie"
				class="w-10 h-10 mr-2"
				play-on-hover
			/>
			{{ sourceTaskDefinition.name }}
		</div>
		<div class="w-full mt-2">
			<SchemaEditorToolbox
				v-model="selectedSchemaDefinition"
				v-model:fragment="editableTaskArtifactFilter.schemaFragment"
				can-select
				hide-save-state
				editable
				can-select-fragment
				clearable
				:hide-default-header="!editableTaskArtifactFilter.include_json"
				:previewing="!!editableTaskArtifactFilter.schemaFragment && editableTaskArtifactFilter.include_json"
				placeholder="Include All Data"
				@update:model-value="onUpdate"
				@update:fragment="onUpdate"
			>
				<template #header-start>
					<LoadingSandLottie class="w-8 h-8 opacity-0 ml-1" :class="{'opacity-100': isSaving}" />
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
							v-model="editableTaskArtifactFilter.include_meta"
							label="Include Meta?"
							class="text-slate-500"
							@update:model-value="onUpdate"
						/>
					</div>
					<div>
						<QCheckbox
							v-model="editableTaskArtifactFilter.include_json"
							label="Include JSON?"
							class="text-slate-500"
							@update:model-value="onUpdate"
						/>
					</div>
				</template>
				<div v-if="editableTaskArtifactFilter.include_meta" class="px-16 mt-4">
					<div>Meta fragment selector</div>
					<FragmentSelectorConfigField
						v-model="editableTaskArtifactFilter.meta_fragment_selector"
						:delay="500"
						@update:model-value="onUpdate"
					/>
				</div>
			</SchemaEditorToolbox>
		</div>
	</div>
</template>
<script setup lang="ts">
import LoadingSandLottie from "@/assets/dotlottie/LoadingSandLottie";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { loadSchemaDefinitions, schemaDefinitions } from "@/components/Modules/Schemas/SchemaDefinitions/store";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { dxTaskArtifactFilter } from "@/components/Modules/TaskDefinitions/TaskArtifactFilters/config";
import { TaskRunnerClasses } from "@/components/Modules/TaskDefinitions/TaskRunners";
import FragmentSelectorConfigField
	from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Fields/FragmentSelectorConfigField";
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
	include_meta: true,
	schemaFragment: null,
	meta_fragment_selector: null
});

const selectedSchemaDefinition = shallowRef<SchemaDefinition>(null);

onMounted(async () => {
	await loadSchemaDefinitions();
	if (props.taskArtifactFilter?.schemaFragment) {
		selectedSchemaDefinition.value = schemaDefinitions.value.find(sd => sd.id === props.taskArtifactFilter.schemaFragment.schema_definition_id);
	}
});
async function onUpdate() {
	isSaving.value = true;

	const input = {
		include_text: editableTaskArtifactFilter.value.include_text,
		include_files: editableTaskArtifactFilter.value.include_files,
		include_json: editableTaskArtifactFilter.value.include_json,
		include_meta: editableTaskArtifactFilter.value.include_meta,
		schema_fragment_id: (selectedSchemaDefinition.value && editableTaskArtifactFilter.value.schemaFragment?.id) || null,
		meta_fragment_selector: editableTaskArtifactFilter.value.meta_fragment_selector
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
