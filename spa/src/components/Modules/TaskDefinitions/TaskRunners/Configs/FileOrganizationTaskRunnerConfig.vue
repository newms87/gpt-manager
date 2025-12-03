<template>
    <BaseTaskRunnerConfig :task-definition="taskDefinition">
        <div class="p-4">
            <div class="text-xl font-medium mb-2">File Organization Configuration</div>
            <div class="text-sm text-slate-600 mb-4">
                This task runner organizes files intelligently by comparing them in groups and suggesting optimal folder
                structures based on their content and relationships.
            </div>

            <!-- Agent Selection -->
            <TaskDefinitionAgentConfigField
                :task-definition="taskDefinition"
                :source-task-definitions="sourceTaskDefinitions"
                class="mb-6"
            />

            <!-- Organization Instructions -->
            <TaskDefinitionPromptField
                :task-definition="taskDefinition"
                label="Organization Instructions"
                placeholder="Describe how files should be grouped and organized. For example: 'Group medical records by patient name' or 'Organize invoices by month and vendor'."
                class="mb-6"
            />

            <!-- Comparison Window Size Field -->
            <NumberField
                v-model="comparisonWindowSize"
                label="Comparison Window Size"
                placeholder="Enter window size..."
                :min="2"
                :max="100"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Number of files to compare at once (2-100):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Smaller window (2-5):</b> Higher page-by-page accuracy, but slower and more expensive</li>
                    <li><b>Medium window (6-20):</b> Good balance of context and accuracy for most documents</li>
                    <li><b>Larger window (21-100):</b> Best for documents with clear patterns, fastest and cheapest</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Start with 3-5, increase if documents have clear patterns</li>
                </ul>
            </div>

            <!-- Comparison Window Overlap Field -->
            <NumberField
                v-model="comparisonWindowOverlap"
                label="Comparison Window Overlap"
                placeholder="Enter overlap size..."
                :min="1"
                :max="99"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Number of files that overlap between consecutive windows (1-windowSize-1):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Small overlap (1-2):</b> Faster processing with fewer windows, good for distinct documents</li>
                    <li><b>Large overlap (3+):</b> More context for boundary decisions, better for similar/ambiguous documents</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Start with 1, increase if documents at window boundaries are being misclassified</li>
                </ul>
            </div>

            <!-- Max Sliding Iterations Field -->
            <NumberField
                v-model="maxSlidingIterations"
                label="Max Sliding Iterations"
                placeholder="Enter max iterations..."
                :min="1"
                :max="5"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Maximum agent calls per window (initial + left slide + right slide):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Lower value (1-2):</b> Faster and cheaper, suitable for clear grouping patterns</li>
                    <li><b>Higher value (3-5):</b> Better accuracy for ambiguous files, but higher cost</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Start with 3 for balanced accuracy and cost</li>
                </ul>
            </div>

            <div class="text-lg font-medium mb-3 mt-6">Resolution Settings</div>

            <!-- Group Confidence Threshold Field -->
            <NumberField
                v-model="groupConfidenceThreshold"
                label="Group Confidence Threshold"
                placeholder="Enter confidence threshold..."
                :min="1"
                :max="5"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Files with group name confidence below this threshold are candidates for adjacency-based resolution:</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Lower value (1-2):</b> Stricter - fewer files eligible for reassignment based on adjacency</li>
                    <li><b>Higher value (3-5):</b> Looser - more files can be reassigned based on adjacency</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Start with 3 for balanced confidence filtering</li>
                </ul>
            </div>

            <!-- Adjacency Boundary Threshold Field -->
            <NumberField
                v-model="adjacencyBoundaryThreshold"
                label="Adjacency Boundary Threshold"
                placeholder="Enter boundary threshold..."
                :min="0"
                :max="5"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Files with belongs_to_previous score at or below this are considered potential boundaries when their group confidence is also low:</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Lower value (0-1):</b> Only very clear boundaries detected</li>
                    <li><b>Higher value (2-5):</b> More aggressive boundary detection</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Start with 2 for moderate boundary detection</li>
                </ul>
            </div>

            <div class="text-lg font-medium mb-3 mt-6">Output Settings</div>

            <!-- Name Similarity Threshold Field -->
            <NumberField
                v-model="nameSimilarityThreshold"
                label="Name Similarity Threshold"
                placeholder="Enter similarity threshold..."
                :min="0.0"
                :max="1.0"
                :step="0.1"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>How similar group names must be to auto-merge (0.0-1.0):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Lower value (0.3-0.5):</b> More aggressive merging of similar names</li>
                    <li><b>Higher value (0.7-0.9):</b> Stricter matching required for merging</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Start with 0.7 for conservative merging</li>
                </ul>
            </div>

            <!-- Blank Page Handling Field -->
            <SelectField
                v-model="blankPageHandling"
                label="Blank Page Handling"
                :options="[
                    { label: 'Join Previous Group', value: 'join_previous' },
                    { label: 'Create Separate Group', value: 'create_blank_group' },
                    { label: 'Discard (Remove from output)', value: 'discard' }
                ]"
                prepend-label
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>How to handle pages/files with no identifiable group name:</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Join Previous Group:</b> Attach blank pages to the most recent group (default)</li>
                    <li><b>Create Separate Group:</b> Create a dedicated group for blank pages</li>
                    <li><b>Discard:</b> Remove blank pages from the output entirely</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> Join Previous Group for most use cases</li>
                </ul>
            </div>
        </div>
    </BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { NumberField, SelectField } from "quasar-ui-danx";
import { computed, ref } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { TaskDefinitionAgentConfigField, TaskDefinitionPromptField } from "./Fields";

export interface FileOrganizationTaskRunnerConfig {
    comparison_window_size: number;
    comparison_window_overlap: number;
    group_confidence_threshold: number;
    adjacency_boundary_threshold: number;
    max_sliding_iterations: number;
    name_similarity_threshold: number;
    blank_page_handling: "join_previous" | "create_blank_group" | "discard";
}

const props = defineProps<{
    taskDefinition: TaskDefinition;
    sourceTaskDefinitions?: TaskDefinition[];
}>();

const config = computed(() => (props.taskDefinition.task_runner_config || {}) as FileOrganizationTaskRunnerConfig);
const comparisonWindowSize = ref(config.value.comparison_window_size ?? 3);
const comparisonWindowOverlap = ref(config.value.comparison_window_overlap ?? 1);
const groupConfidenceThreshold = ref(config.value.group_confidence_threshold ?? 3);
const adjacencyBoundaryThreshold = ref(config.value.adjacency_boundary_threshold ?? 2);
const maxSlidingIterations = ref(config.value.max_sliding_iterations ?? 3);
const nameSimilarityThreshold = ref(config.value.name_similarity_threshold ?? 0.7);
const blankPageHandling = ref(config.value.blank_page_handling ?? "join_previous");

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debounceChange = useDebounceFn(() => {
    updateTaskDefinitionAction.trigger(props.taskDefinition, {
        task_runner_config: {
            ...config.value,
            comparison_window_size: Number(comparisonWindowSize.value) || 3,
            comparison_window_overlap: Number(comparisonWindowOverlap.value) || 1,
            group_confidence_threshold: Number(groupConfidenceThreshold.value) || 3,
            adjacency_boundary_threshold: Number(adjacencyBoundaryThreshold.value) || 2,
            max_sliding_iterations: Number(maxSlidingIterations.value) || 3,
            name_similarity_threshold: Number(nameSimilarityThreshold.value) || 0.7,
            blank_page_handling: blankPageHandling.value || "join_previous"
        }
    });
}, 500);
</script>
