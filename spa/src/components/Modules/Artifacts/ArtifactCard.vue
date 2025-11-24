<template>
    <div class="bg-slate-900 p-2 rounded-lg">
        <div class="flex-x mb-2 space-x-2 w-full max-w-full overflow-hidden">
            <LabelPillWidget :label="idLabel" color="sky" size="xs" class="flex-shrink-0" />
            <LabelPillWidget
                v-if="artifact.task_process_id"
                :label="`pid: ${artifact.task_process_id}`"
                color="sky"
                size="xs"
                class="cursor-pointer hover:outline outline-2 outline-sky-700 underline hover:text-sky-300 whitespace-nowrap"
                :class="{'outline !outline-4 outline-sky-400 !text-sky-300': isShowingTaskProcess}"
                @click="toggleShowTaskProcess"
            />
            <LabelPillWidget :label="fDateTime(artifact.created_at)" color="blue" size="xs" class="flex-shrink-0" />
            <LabelPillWidget :label="artifact.position" color="green" size="xs" class="flex-shrink-0" />
            <div class="flex-grow min-w-0 overflow-hidden">{{ artifact.name }}</div>
            <ShowHideButton
                v-if="artifact.text_content"
                v-model="isShowingText"
                class="bg-green-900 flex-shrink-0"
                size="sm"
                :show-icon="TextIcon"
                tooltip="Show Text"
            />
            <ShowHideButton
                v-if="artifact.files?.length > 0"
                v-model="isShowingFiles"
                class="bg-amber-900 flex-shrink-0"
                size="sm"
                :show-icon="FilesIcon"
                tooltip="Show Files"
            />
            <ShowHideButton
                v-if="artifact.json_content"
                v-model="isShowingJson"
                class="bg-purple-700 flex-shrink-0"
                size="sm"
                :show-icon="JsonIcon"
                tooltip="Show Json Content"
            />
            <ShowHideButton
                v-if="artifact.meta"
                v-model="isShowingMeta"
                class="bg-slate-500 text-slate-300 flex-shrink-0"
                size="sm"
                :show-icon="MetaIcon"
                tooltip="Show Artifact Meta"
            />
            <ShowHideButton
                v-if="hasGroup"
                v-model="isShowingGroup"
                class="bg-indigo-700 flex-shrink-0"
                size="sm"
                :show-icon="GroupIcon"
                tooltip="Show Child Artifacts"
                :label="artifact.child_artifacts_count"
            />
            <ShowHideButton
                v-if="typeCount > 1"
                :model-value="isShowingAll"
                class="bg-sky-900 flex-shrink-0"
                size="sm"
                tooltip="Show All Data"
                @update:model-value="onToggleAll()"
            />
        </div>
        <ListTransition>
            <div v-if="isShowingTaskProcess">
                <NodeTaskProcessCard v-if="taskProcess" :task-process="taskProcess" class="bg-slate-700 p-4" />
                <QSkeleton v-else class="h-20 w-full" />
            </div>
            <div v-if="hasFiles && isShowingFiles">
                <div class="flex items-stretch justify-start flex-wrap gap-2">
                    <div
                        v-for="file in artifact.files"
                        :key="'file-upload-' + file.id"
                        class="flex flex-col items-center"
                    >
                        <FilePreview
                            class="cursor-pointer bg-gray-200 w-32 h-32"
                            :file="file"
                            :related-files="artifact.files"
                            downloadable
                        />
                        <div
                            class="text-xs text-gray-400 mt-1 w-32 text-center truncate"
                            :title="(file.page_number) + '. ' + file.filename"
                        >
                            {{ file.page_number }}. {{ file.filename }}
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="hasText && isShowingText">
                <MarkdownEditor
                    :model-value="artifact.text_content"
                    format="text"
                    readonly
                />
            </div>
            <div v-if="hasJson && isShowingJson">
                <MarkdownEditor
                    :model-value="artifact.json_content"
                    format="yaml"
                    readonly
                />
            </div>
            <div v-if="hasMeta && isShowingMeta">
                <MarkdownEditor
                    :model-value="artifact.meta"
                    format="yaml"
                    readonly
                />
            </div>
            <ArtifactList
                v-if="hasGroup && isShowingGroup"
                :filter="{parent_artifact_id: artifact.id}"
                dense
                class="bg-slate-800 p-4"
                :level="(level||0)+1"
            />
        </ListTransition>
    </div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import NodeTaskProcessCard from "@/components/Modules/WorkflowCanvas/NodeTaskProcessCard";
import { useStoredFileUpdates } from "@/composables/useStoredFileUpdates";
import { Artifact, TaskProcess } from "@/types";
import {
    FaSolidBarcode as MetaIcon,
    FaSolidDatabase as JsonIcon,
    FaSolidFile as FilesIcon,
    FaSolidLayerGroup as GroupIcon,
    FaSolidT as TextIcon
} from "danx-icon";
import { fDateTime, FilePreview, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, shallowRef, watch } from "vue";

const props = defineProps<{
    artifact: Artifact,
    show?: boolean;
    showText?: boolean;
    showFiles?: boolean;
    showJson?: boolean;
    showMeta?: boolean;
    showGroup?: boolean;
    level?: number;
}>();

const hasText = computed(() => !!props.artifact.text_content);
const hasFiles = computed(() => !!props.artifact.files?.length);
const hasJson = computed(() => !!props.artifact.json_content);
const hasMeta = computed(() => !!props.artifact.meta);
const hasGroup = computed(() => (props.artifact.child_artifacts_count || 0) > 0);
const typeCount = computed(() => [hasText.value, hasJson.value, hasFiles.value, hasGroup.value].filter(Boolean).length);
const isShowingText = ref(props.showText);
const isShowingFiles = ref(props.showFiles);
const isShowingJson = ref(props.showJson);
const isShowingMeta = ref(props.showMeta);
const isShowingGroup = ref(props.showGroup);
const isShowingTaskProcess = ref(false);

const idLabel = computed(() => "Artifact: " + (props.artifact.original_artifact_id ? props.artifact.original_artifact_id + " -> " : "") + props.artifact.id);

const isShowingAll = computed(() =>
    (!hasText.value || isShowingText.value) &&
    (!hasFiles.value || isShowingFiles.value) &&
    (!hasJson.value || isShowingJson.value) &&
    (!hasMeta.value || isShowingMeta.value) &&
    (!hasGroup.value || isShowingGroup.value)
);

function onToggleAll(state: boolean = null) {
    state = state === null ? !isShowingAll.value : state;
    isShowingText.value = state;
    isShowingFiles.value = state;
    isShowingJson.value = state;
    isShowingMeta.value = state;
    isShowingGroup.value = state;
}

watch(() => props.show, onToggleAll);
watch(() => props.showText, (state) => {
    isShowingText.value = state;
});
watch(() => props.showFiles, (state) => {
    isShowingFiles.value = state;
});
watch(() => props.showJson, (state) => {
    isShowingJson.value = state;
});
watch(() => props.showMeta, (state) => {
    isShowingMeta.value = state;
});
watch(() => props.showGroup, (state) => {
    isShowingGroup.value = state;
});

const taskProcess = shallowRef<TaskProcess>(null);

async function toggleShowTaskProcess() {
    if (!props.artifact.task_process_id) return;

    isShowingTaskProcess.value = !isShowingTaskProcess.value;

    if (isShowingTaskProcess.value) {
        taskProcess.value = await dxTaskProcess.routes.details({ id: props.artifact.task_process_id } as TaskProcess) as TaskProcess;
    }
}

// Subscribe to file updates for real-time transcoding progress
const { subscribeToFileUpdates, unsubscribeFromFileUpdates } = useStoredFileUpdates();

// Subscribe to files when they're actually visible (isShowingFiles is true)
watch([() => props.artifact.files, isShowingFiles], ([files, showing]) => {
    if (showing && files?.length > 0) {
        files.forEach(file => subscribeToFileUpdates(file));
    } else if (!showing && files?.length > 0) {
        // Unsubscribe when files are hidden
        files.forEach(file => unsubscribeFromFileUpdates(file));
    }
});
</script>
