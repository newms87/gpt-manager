<template>
    <QCard
        :class="themeClass('bg-slate-700', 'bg-white border border-slate-200')"
        class="overflow-hidden shadow-lg"
    >
        <!-- Header Section -->
        <div class="p-3">
            <!-- Top row: ID, Position, Model, Timestamp -->
            <div class="flex items-center gap-2 flex-wrap mb-2">
                <LabelPillWidget
                    :label="`#${artifact.id}`"
                    :color="themeClass('sky', 'sky-soft')"
                    size="xs"
                />
                <LabelPillWidget
                    :label="`pos: ${artifact.position}`"
                    :color="themeClass('emerald', 'emerald-soft')"
                    size="xs"
                />
                <LabelPillWidget
                    v-if="artifact.model"
                    :label="artifact.model"
                    :color="themeClass('purple', 'purple-soft')"
                    size="xs"
                />
                <div class="flex-1" />
                <LabelPillWidget
                    :label="fDateTime(artifact.created_at)"
                    :color="themeClass('slate', 'slate-soft')"
                    size="xs"
                />
            </div>

            <!-- Artifact Name row with icon buttons on right -->
            <div class="flex items-start gap-2">
                <div class="flex-1 min-w-0">
                    <!-- Artifact Name (prominent, 2-line clamp) -->
                    <h3
                        :class="themeClass('text-slate-100', 'text-slate-800')"
                        class="text-sm font-semibold line-clamp-2 mb-1"
                    >
                        {{ artifact.name }}
                    </h3>

                    <!-- Transform indicator if original_artifact_id -->
                    <div v-if="artifact.original_artifact_id" class="flex items-center gap-1 text-xs">
                        <LabelPillWidget
                            :label="`Transformed from #${artifact.original_artifact_id}`"
                            :color="themeClass('amber', 'amber-soft')"
                            size="xs"
                        />
                    </div>
                </div>

                <!-- Action buttons on right side -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <!-- Text button -->
                    <ActionButton
                        v-if="hasText"
                        :icon="TextIcon"
                        :color="themeClass('green-invert', 'green-soft')"
                        size="md"
                        :tooltip="`Text ${textCount}`"
                        @click="emit('open-dialog', artifact, 'text')"
                    >
                        <span
                            :class="themeClass('text-slate-400', 'text-slate-500')"
                            class="ml-1 text-sm font-medium"
                        >
                            {{ textCount }}
                        </span>
                    </ActionButton>

                    <!-- Files button -->
                    <ActionButton
                        v-if="hasFiles"
                        :icon="FilesIcon"
                        :color="themeClass('orange', 'orange')"
                        size="md"
                        :tooltip="`Files ${filesCount}`"
                        @click="emit('open-dialog', artifact, 'files')"
                    >
                        <span
                            :class="themeClass('text-slate-400', 'text-slate-500')"
                            class="ml-1 text-sm font-medium"
                        >
                            {{ filesCount }}
                        </span>
                    </ActionButton>

                    <!-- JSON button -->
                    <ActionButton
                        v-if="hasJson"
                        :icon="JsonIcon"
                        :color="themeClass('purple', 'purple')"
                        size="md"
                        :tooltip="`JSON ${jsonCount}`"
                        @click="emit('open-dialog', artifact, 'json')"
                    >
                        <span
                            :class="themeClass('text-slate-400', 'text-slate-500')"
                            class="ml-1 text-sm font-medium"
                        >
                            {{ jsonCount }}
                        </span>
                    </ActionButton>

                    <!-- Meta button -->
                    <ActionButton
                        v-if="hasMeta"
                        :icon="MetaIcon"
                        :color="themeClass('slate-invert', 'slate-soft')"
                        size="md"
                        :tooltip="`Meta ${metaCount}`"
                        @click="emit('open-dialog', artifact, 'meta')"
                    >
                        <span
                            :class="themeClass('text-slate-400', 'text-slate-500')"
                            class="ml-1 text-sm font-medium"
                        >
                            {{ metaCount }}
                        </span>
                    </ActionButton>

                    <!-- Children button -->
                    <ActionButton
                        v-if="hasGroup"
                        :icon="GroupIcon"
                        :color="themeClass('blue-invert', 'blue-soft')"
                        size="md"
                        :tooltip="`Children ${childrenCount}`"
                        @click="emit('open-dialog', artifact, 'children')"
                    >
                        <span
                            :class="themeClass('text-slate-400', 'text-slate-500')"
                            class="ml-1 text-sm font-medium"
                        >
                            {{ childrenCount }}
                        </span>
                    </ActionButton>
                </div>
            </div>
        </div>

        <!-- Metadata Bar (if task_process_id) -->
        <div
            v-if="artifact.task_process_id"
            :class="themeClass('bg-slate-800/50 border-slate-600', 'bg-slate-50 border-slate-200')"
            class="border-t border-b px-3 py-2"
        >
            <LabelPillWidget
                :label="`Process #${artifact.task_process_id}`"
                :color="themeClass('cyan', 'cyan-soft')"
                size="xs"
                class="cursor-pointer hover:outline outline-2 outline-cyan-500"
                :class="{'outline !outline-4 !outline-cyan-400': isShowingTaskProcess}"
                @click="toggleShowTaskProcess"
            />
        </div>

        <!-- Task Process Expandable Section (kept inline since it's context, not content) -->
        <QSlideTransition>
            <div v-if="isShowingTaskProcess" :class="[themeClass('bg-slate-800 border-slate-600', 'bg-slate-100 border-slate-200'), 'border-t']">
                <div :class="themeClass('bg-slate-700 border-slate-600', 'bg-white border-slate-200')" class="border-b px-3 py-2 flex items-center justify-between">
                    <span :class="themeClass('text-cyan-300', 'text-cyan-700')" class="font-medium text-sm">Task Process</span>
                    <ActionButton
                        type="cancel"
                        color="slate"
                        size="xs"
                        tooltip="Close task process"
                        @click="isShowingTaskProcess = false"
                    />
                </div>
                <div class="p-3">
                    <NodeTaskProcessCard v-if="taskProcess" :task-process="taskProcess" :class="themeClass('bg-slate-600', 'bg-white')" class="p-3" />
                    <QSkeleton v-else class="h-20 w-full" />
                </div>
            </div>
        </QSlideTransition>
    </QCard>
</template>

<script setup lang="ts">
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import NodeTaskProcessCard from "@/components/Modules/WorkflowCanvas/NodeTaskProcessCard";
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import { Artifact, TaskProcess } from "@/types";
import {
    FaSolidCircleInfo as MetaIcon,
    FaSolidCode as JsonIcon,
    FaSolidFileLines as TextIcon,
    FaSolidFolder as FilesIcon,
    FaSolidLayerGroup as GroupIcon
} from "danx-icon";
import { ActionButton, fDateTime, LabelPillWidget } from "quasar-ui-danx";
import { QCard, QSkeleton, QSlideTransition } from "quasar";
import { computed, ref, shallowRef } from "vue";

type ContentTab = "text" | "files" | "json" | "meta" | "children";

const props = withDefaults(defineProps<{
    artifact: Artifact;
    level?: number;
}>(), {
    level: 0
});

const emit = defineEmits<{
    "open-dialog": [artifact: Artifact, tab: ContentTab];
}>();

const { themeClass } = useAuditCardTheme();

// Content type checks
const hasText = computed(() => !!props.artifact.text_content);
const hasFiles = computed(() => !!props.artifact.files?.length);
const hasJson = computed(() => !!props.artifact.json_content);
const hasMeta = computed(() => !!props.artifact.meta);
const hasGroup = computed(() => (props.artifact.child_artifacts_count || 0) > 0);

// Content counts
const textCount = computed(() => {
    if (!props.artifact.text_content) return 0;
    return props.artifact.text_content.length;
});

const filesCount = computed(() => props.artifact.files?.length || 0);

const jsonCount = computed(() => {
    if (!props.artifact.json_content) return 0;
    if (Array.isArray(props.artifact.json_content)) {
        return props.artifact.json_content.length;
    }
    return Object.keys(props.artifact.json_content).length;
});

const metaCount = computed(() => {
    if (!props.artifact.meta) return 0;
    if (Array.isArray(props.artifact.meta)) {
        return props.artifact.meta.length;
    }
    return Object.keys(props.artifact.meta).length;
});

const childrenCount = computed(() => props.artifact.child_artifacts_count || 0);

// Task process state
const isShowingTaskProcess = ref(false);
const taskProcess = shallowRef<TaskProcess>(null);

async function toggleShowTaskProcess() {
    if (!props.artifact.task_process_id) return;

    isShowingTaskProcess.value = !isShowingTaskProcess.value;

    if (isShowingTaskProcess.value) {
        taskProcess.value = await dxTaskProcess.routes.details({ id: props.artifact.task_process_id } as TaskProcess) as TaskProcess;
    }
}
</script>
