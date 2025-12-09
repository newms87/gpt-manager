<template>
    <BaseTaskRunnerConfig :task-definition="taskDefinition">
        <div class="p-4">
            <div class="text-xl font-medium mb-2">Extract Data Configuration</div>
            <div class="text-sm text-slate-600 mb-4">
                This task runner extracts structured data from documents by intelligently grouping data points and
                using smart search strategies to find information efficiently.
            </div>

            <!-- Agent Selection -->
            <TaskDefinitionAgentConfigField
                :task-definition="taskDefinition"
                :source-task-definitions="sourceTaskDefinitions"
                class="mb-6"
            />

            <div class="text-lg font-medium mb-3 mt-6">Search Strategy</div>

            <!-- Global Search Mode Field -->
            <div class="flex-x gap-4 mb-2">
                <div class="font-bold">Global Search Mode:</div>
                <QTabs
                    v-model="globalSearchMode"
                    class="tab-buttons border-sky-900 text-sky-200"
                    indicator-color="sky-900"
                    @update:model-value="debounceChange"
                >
                    <QTab name="intelligent" label="Intelligent" />
                    <QTab name="skim_only" label="Skim Only" />
                    <QTab name="exhaustive_only" label="Exhaustive" />
                </QTabs>
            </div>
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <template v-if="globalSearchMode === 'intelligent'">
                    <p><b>Intelligent:</b> LLM chooses skim or exhaustive mode per data point group based on complexity.</p>
                    <p class="mt-1 text-gray-600"><b>Recommended for most use cases</b> - balances speed and thoroughness by letting the AI decide the best approach for each group.</p>
                </template>
                <template v-else-if="globalSearchMode === 'skim_only'">
                    <p><b>Skim Only:</b> Stop processing early when all fields reach confidence threshold.</p>
                    <p class="mt-1 text-gray-600"><b>Faster and cheaper</b> - best when speed matters more than exhaustive coverage.</p>
                </template>
                <template v-else>
                    <p><b>Exhaustive:</b> Always process all pages regardless of confidence.</p>
                    <p class="mt-1 text-gray-600"><b>Slower but most thorough</b> - best when you need to ensure no data is missed.</p>
                </template>
            </div>

            <!-- Confidence Threshold Field - Only show if not exhaustive_only -->
            <div v-if="globalSearchMode !== 'exhaustive_only'" class="mb-2">
                <div class="text-sm font-medium mb-2 text-slate-300">Confidence Threshold: {{ confidenceThreshold }}</div>
                <QSlider
                    v-model="confidenceThreshold"
                    :min="1"
                    :max="5"
                    :step="1"
                    snap
                    markers
                    label
                    color="sky"
                    class="mb-2"
                    @update:model-value="debounceChange"
                />
            </div>
            <div v-if="globalSearchMode !== 'exhaustive_only'" class="text-xs text-gray-500 mb-6 ml-1">
                <p>For skim mode, stop early when all fields reach this confidence level (1-5):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Lower value (1-2):</b> Stop earlier with less certainty (faster, less accurate)</li>
                    <li><b>Higher value (4-5):</b> Continue until very confident (slower, more accurate)</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> 3 for balanced accuracy and speed</li>
                </ul>
            </div>

            <div class="text-lg font-medium mb-3 mt-6">Grouping Configuration</div>

            <!-- Group Max Points Field -->
            <div class="mb-2">
                <div class="text-sm font-medium mb-2 text-slate-300">Maximum Data Points Per Group: {{ groupMaxPoints }}</div>
                <QSlider
                    v-model="groupMaxPoints"
                    :min="1"
                    :max="50"
                    :step="1"
                    snap
                    :marker-labels="{ 1: '1', 5: '5', 10: '10', 20: '20', 30: '30', 40: '40', 50: '50' }"
                    label
                    color="sky"
                    class="mb-2"
                    @update:model-value="debounceChange"
                />
            </div>
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Limits how many data points can be extracted in each group (1-50):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>Smaller groups (1-5):</b> Better for unrelated fields, faster per-group processing</li>
                    <li><b>Larger groups (10-50):</b> Better for related fields, more context for LLM, fewer API calls</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> 10 for most document types</li>
                </ul>
            </div>

            <!-- User Planning Hints Field -->
            <MarkdownEditor
                v-model="userPlanningHints"
                label="Planning Hints (Optional)"
                placeholder="e.g., Group medical and billing codes separately"
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Optional hints to guide how the LLM groups data points during the planning phase:</p>
                <ul class="list-disc pl-5 mt-1">
                    <li>Suggest logical groupings (e.g., "Group patient demographics together")</li>
                    <li>Indicate fields that should be extracted separately</li>
                    <li>Provide domain-specific grouping strategies</li>
                </ul>
            </div>

            <div class="text-lg font-medium mb-3 mt-6">Context Configuration</div>

            <!-- Classification Context Before Field -->
            <div class="mb-2">
                <div class="text-sm font-medium mb-2 text-slate-300">Context Pages Before: {{ classificationContextBefore }}</div>
                <QSlider
                    v-model="classificationContextBefore"
                    :min="0"
                    :max="10"
                    :step="1"
                    snap
                    :marker-labels="{ 0: '0', 2: '2', 4: '4', 6: '6', 8: '8', 10: '10' }"
                    label
                    color="sky"
                    class="mb-2"
                    @update:model-value="debounceChange"
                />
            </div>
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Number of pages before current page to include for context (0-10):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>0 pages:</b> No previous context (fastest)</li>
                    <li><b>1-3 pages:</b> Limited context for field understanding</li>
                    <li><b>4+ pages:</b> More context but slower and more expensive</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> 2 for balanced context</li>
                </ul>
            </div>

            <!-- Classification Context After Field -->
            <div class="mb-2">
                <div class="text-sm font-medium mb-2 text-slate-300">Context Pages After: {{ classificationContextAfter }}</div>
                <QSlider
                    v-model="classificationContextAfter"
                    :min="0"
                    :max="10"
                    :step="1"
                    snap
                    :marker-labels="{ 0: '0', 2: '2', 4: '4', 6: '6', 8: '8', 10: '10' }"
                    label
                    color="sky"
                    class="mb-2"
                    @update:model-value="debounceChange"
                />
            </div>
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Number of pages after current page to include for context (0-10):</p>
                <ul class="list-disc pl-5 mt-1">
                    <li><b>0 pages:</b> No future context (fastest)</li>
                    <li><b>1-2 pages:</b> Limited lookahead for field continuation</li>
                    <li><b>3+ pages:</b> More lookahead but slower and more expensive</li>
                    <li class="mt-1 text-gray-600"><b>Recommended:</b> 1 for most use cases</li>
                </ul>
            </div>

            <div class="text-lg font-medium mb-3 mt-6">Extraction Instructions</div>

            <!-- Extraction Instructions Field -->
            <MarkdownEditor
                v-model="extractionInstructions"
                label="Extraction Instructions (Optional)"
                placeholder="e.g., Ignore any handwritten notes"
                class="mb-2"
                @update:model-value="debounceChange"
            />
            <div class="text-xs text-gray-500 mb-6 ml-1">
                <p>Additional instructions for the extraction LLM:</p>
                <ul class="list-disc pl-5 mt-1">
                    <li>Specify what to ignore (e.g., "Ignore headers and footers")</li>
                    <li>Provide formatting guidance (e.g., "Use ISO date format YYYY-MM-DD")</li>
                    <li>Clarify ambiguous field expectations</li>
                </ul>
            </div>

            <div class="text-lg font-medium mb-3 mt-6">Output Schema</div>

            <SchemaAndFragmentsConfigField class="mt-6" :task-definition="taskDefinition" :max-fragments="1" force-schema />

            <TimeoutConfigField class="mt-6" :task-definition="taskDefinition" />
        </div>
    </BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { QSlider, QTab, QTabs } from "quasar";
import { computed, ref } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { SchemaAndFragmentsConfigField, TaskDefinitionAgentConfigField, TimeoutConfigField } from "./Fields";

export interface ExtractDataTaskRunnerConfig {
    group_max_points: number;
    global_search_mode: "intelligent" | "skim_only" | "exhaustive_only";
    confidence_threshold: number;
    classification_context_before: number;
    classification_context_after: number;
    user_planning_hints?: string;
    extraction_instructions?: string;
}

const props = defineProps<{
    taskDefinition: TaskDefinition;
    sourceTaskDefinitions?: TaskDefinition[];
}>();

const config = computed(() => (props.taskDefinition.task_runner_config || {}) as ExtractDataTaskRunnerConfig);
const groupMaxPoints = ref(config.value.group_max_points ?? 10);
const globalSearchMode = ref(config.value.global_search_mode ?? "intelligent");
const confidenceThreshold = ref(config.value.confidence_threshold ?? 3);
const classificationContextBefore = ref(config.value.classification_context_before ?? 2);
const classificationContextAfter = ref(config.value.classification_context_after ?? 1);
const userPlanningHints = ref(config.value.user_planning_hints ?? "");
const extractionInstructions = ref(config.value.extraction_instructions ?? "");

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debounceChange = useDebounceFn(() => {
    updateTaskDefinitionAction.trigger(props.taskDefinition, {
        task_runner_config: {
            ...config.value,
            group_max_points: Number(groupMaxPoints.value) || 10,
            global_search_mode: globalSearchMode.value || "intelligent",
            confidence_threshold: Number(confidenceThreshold.value) || 3,
            classification_context_before: Number(classificationContextBefore.value) || 2,
            classification_context_after: Number(classificationContextAfter.value) || 1,
            user_planning_hints: userPlanningHints.value || undefined,
            extraction_instructions: extractionInstructions.value || undefined
        }
    });
}, 500);
</script>
