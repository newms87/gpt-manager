<template>
    <div
        class="task-artifact-filter-form p-4 border rounded-md"
        :class="{ 'bg-gray-900 text-gray-500': !props.taskArtifactFilter }"
    >
        <!-- Header with source task info and toggle switch -->
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <Component
                    :is="TaskRunnerClasses.resolve(sourceTaskDefinition.task_runner_name).lottie"
                    class="w-10 h-10 mr-2"
                    play-on-hover
                />
                <span class="font-medium">{{ sourceTaskDefinition.name }}</span>
            </div>

            <div class="flex items-center">
                <QToggle
                    :model-value="!!props.taskArtifactFilter"
                    label="Active"
                    color="primary"
                    @update:model-value="toggleFilterActive"
                />
            </div>
        </div>

        <!-- Filter configuration (only shown when active) -->
        <div v-if="props.taskArtifactFilter" class="w-full transition-all duration-300 mt-4">
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
                    <div class="grid grid-cols-4 gap-4 p-2">
                        <QCheckbox
                            v-model="editableTaskArtifactFilter.include_text"
                            label="Include Text?"
                            class="text-slate-500"
                            @update:model-value="onUpdate"
                        />
                        <QCheckbox
                            v-model="editableTaskArtifactFilter.include_files"
                            label="Include Files?"
                            class="text-slate-500"
                            @update:model-value="onUpdate"
                        />
                        <QCheckbox
                            v-model="editableTaskArtifactFilter.include_meta"
                            label="Include Meta?"
                            class="text-slate-500"
                            @update:model-value="onUpdate"
                        />
                        <QCheckbox
                            v-model="editableTaskArtifactFilter.include_json"
                            label="Include JSON?"
                            class="text-slate-500"
                            @update:model-value="onUpdate"
                        />
                    </div>
                </template>
                <div v-if="editableTaskArtifactFilter.include_meta" class="px-6 mt-4">
                    <div class="font-medium mb-2">Meta fragment selector</div>
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
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";

import { loadSchemaDefinitions, schemaDefinitions } from "@/components/Modules/SchemaEditor/store";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { dxTaskArtifactFilter } from "@/components/Modules/TaskDefinitions/TaskArtifactFilters/config";
import { TaskRunnerClasses } from "@/components/Modules/TaskDefinitions/TaskRunners";

import type { SchemaDefinition, TaskArtifactFilter, TaskDefinition } from "@/types";
import { onMounted, ref, shallowRef } from "vue";
import FragmentSelectorConfigField from "./FragmentSelectorConfigField.vue";

const props = defineProps<{
    sourceTaskDefinition: TaskDefinition;
    targetTaskDefinition: TaskDefinition;
    taskArtifactFilter?: TaskArtifactFilter;
}>();

const isSaving = ref(false);
const createArtifactFilter = dxTaskArtifactFilter.getAction("create");
const updateArtifactFilter = dxTaskArtifactFilter.getAction("update");
const deleteArtifactFilter = dxTaskArtifactFilter.getAction("delete");

// Default filter state with all options enabled
const defaultFilterState = {
    include_text: true,
    include_files: true,
    include_json: true,
    include_meta: true,
    schemaFragment: null,
    meta_fragment_selector: null
};

const editableTaskArtifactFilter = ref<Partial<TaskArtifactFilter>>(props.taskArtifactFilter || defaultFilterState);

const selectedSchemaDefinition = shallowRef<SchemaDefinition>(null);

onMounted(async () => {
    await loadSchemaDefinitions();
    if (props.taskArtifactFilter?.schemaFragment) {
        selectedSchemaDefinition.value = schemaDefinitions.value.find(sd => sd.id === props.taskArtifactFilter.schemaFragment.schema_definition_id);
    }
});

/**
 * Toggles the filter active state
 * - When activating: Creates a new filter with default state
 * - When deactivating: Deletes the existing filter
 */
async function toggleFilterActive(isActive: boolean) {
    isSaving.value = true;

    try {
        if (isActive) {
            // Create new filter with default settings (everything allowed)
            await createArtifactFilter.trigger(null, {
                source_task_definition_id: props.sourceTaskDefinition.id,
                target_task_definition_id: props.targetTaskDefinition.id,
                ...defaultFilterState
            });
        } else if (props.taskArtifactFilter) {
            // Delete existing filter
            await deleteArtifactFilter.trigger(props.taskArtifactFilter);
        }

        // Refresh the task definition to update the UI
        await dxTaskDefinition.routes.details(props.targetTaskDefinition, {
            taskArtifactFiltersAsTarget: true
        });
    } catch (error) {
        console.error("Error toggling filter state:", error);
    } finally {
        isSaving.value = false;
    }
}

/**
 * Updates the existing task artifact filter
 */
async function onUpdate() {
    isSaving.value = true;

    try {
        const input = {
            include_text: editableTaskArtifactFilter.value.include_text,
            include_files: editableTaskArtifactFilter.value.include_files,
            include_json: editableTaskArtifactFilter.value.include_json,
            include_meta: editableTaskArtifactFilter.value.include_meta,
            schema_fragment_id: (selectedSchemaDefinition.value && editableTaskArtifactFilter.value.schemaFragment?.id) || null,
            meta_fragment_selector: editableTaskArtifactFilter.value.meta_fragment_selector
        };

        if (props.taskArtifactFilter) {
            await updateArtifactFilter.trigger(props.taskArtifactFilter, input);
        }
    } catch (error) {
        console.error("Error updating filter:", error);
    } finally {
        isSaving.value = false;
    }
}
</script>

<style scoped lang="scss">
.task-artifact-filter-form {
    transition: all 0.3s ease;

    &:hover {
        box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
    }
}
</style>
