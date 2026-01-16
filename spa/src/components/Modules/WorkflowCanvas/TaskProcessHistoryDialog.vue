<template>
    <InfoDialog
        v-if="isShowing"
        :title="`Process Restart History - pid: ${taskProcess.id}`"
        content-class="w-[85vw] h-[80vh] bg-slate-950"
        @close="$emit('close')"
    >
        <div class="h-full flex flex-col overflow-hidden">
            <!-- Current Process Info -->
            <div class="bg-slate-800 p-4 rounded-lg mb-4">
                <div class="flex items-center space-x-2 mb-2">
                    <LabelPillWidget label="Current" color="green" size="sm" />
                    <span class="text-slate-300 font-medium">{{ taskProcess.activity }}</span>
                </div>
                <div class="flex items-center space-x-3 text-sm">
                    <LabelPillWidget :label="`pid: ${taskProcess.id}`" color="sky" size="xs" />
                    <WorkflowStatusTimerPill :runner="taskProcess" />
                    <span class="text-slate-400">Restarts: {{ taskProcess.restart_count || 0 }}</span>
                </div>
            </div>

            <!-- History Section -->
            <div class="flex-grow overflow-y-auto">
                <div v-if="isLoading" class="flex items-center justify-center py-12">
                    <QSpinnerGears class="text-orange-500 w-10 h-10" />
                    <p class="ml-3 text-slate-400">Loading history...</p>
                </div>

                <div v-else-if="historicalProcesses.length === 0" class="text-center py-12">
                    <FaSolidClockRotateLeft class="w-12 h-12 text-slate-600 mx-auto mb-3" />
                    <p class="text-slate-500">No previous attempts found</p>
                </div>

                <div v-else class="space-y-4">
                    <div class="text-sm text-slate-400 mb-2">
                        Previous Attempts ({{ historicalProcesses.length }})
                    </div>
                    <div
                        v-for="(process, index) in historicalProcesses"
                        :key="process.id"
                        class="bg-slate-900 rounded-lg p-4 border border-slate-700"
                    >
                        <!-- Process Header -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <LabelPillWidget
                                    :label="`Attempt ${historicalProcesses.length - index}`"
                                    color="orange"
                                    size="sm"
                                />
                                <LabelPillWidget :label="`pid: ${process.id}`" color="sky" size="xs" />
                            </div>
                            <WorkflowStatusTimerPill :runner="process" />
                        </div>

                        <!-- Process Activity -->
                        <div class="text-slate-300 text-sm mb-3">
                            {{ process.activity }}
                        </div>

                        <!-- Process Details -->
                        <div class="flex items-center flex-wrap gap-2 text-xs">
                            <LabelPillWidget
                                v-if="process.operation"
                                :label="process.operation"
                                :color="getOperationColor(process.operation)"
                                size="xs"
                            />
                            <span class="text-slate-500">
								Created: {{ fDateTime(process.created_at) }}
							</span>
                            <span v-if="process.started_at" class="text-slate-500">
								| Started: {{ fDateTime(process.started_at) }}
							</span>
                            <span v-if="process.completed_at || process.failed_at" class="text-slate-500">
								| Ended: {{ fDateTime(process.completed_at || process.failed_at) }}
							</span>
                        </div>

                        <!-- Expandable Sections -->
                        <div class="mt-3 space-y-2">
                            <!-- Output Artifacts -->
                            <div v-if="process.output_artifact_count > 0">
                                <ShowHideButton
                                    v-model="expandedSections[`artifacts-${process.id}`]"
                                    :label="`Output Artifacts (${process.output_artifact_count})`"
                                    class="text-xs"
                                    color="green"
                                    size="xs"
                                    @show="loadProcessArtifacts(process)"
                                />
                                <div
                                    v-if="expandedSections[`artifacts-${process.id}`]"
                                    class="mt-2 bg-slate-800 rounded p-2"
                                >
                                    <QSkeleton v-if="loadingArtifacts[process.id]" class="h-16" />
                                    <ArtifactList
                                        v-else-if="process.outputArtifacts"
                                        dense
                                        :filter="{taskProcess: {category: 'output', artifactable_id: process.id}}"
                                    />
                                </div>
                            </div>

                            <!-- Job Dispatches -->
                            <div v-if="process.job_dispatch_count > 0">
                                <ShowHideButton
                                    v-model="expandedSections[`jobs-${process.id}`]"
                                    :label="`Job Dispatches (${process.job_dispatch_count})`"
                                    class="text-xs"
                                    color="slate"
                                    size="xs"
                                    @show="loadProcessJobDispatches(process)"
                                />
                                <div
                                    v-if="expandedSections[`jobs-${process.id}`]"
                                    class="mt-2 bg-slate-800 rounded p-2"
                                >
                                    <QSkeleton v-if="loadingJobDispatches[process.id]" class="h-16" />
                                    <JobDispatchList
                                        v-else-if="process.jobDispatches"
                                        :jobs="process.jobDispatches"
                                    />
                                </div>
                            </div>

                            <!-- Agent Thread -->
                            <div v-if="process.agentThread">
                                <ShowHideButton
                                    v-model="expandedSections[`thread-${process.id}`]"
                                    label="Agent Thread"
                                    class="text-xs"
                                    color="sky"
                                    size="xs"
                                />
                                <div
                                    v-if="expandedSections[`thread-${process.id}`]"
                                    class="mt-2 bg-slate-800 rounded p-2"
                                >
                                    <TaskProcessAgentThreadCard :agent-thread="process.agentThread" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </InfoDialog>
</template>

<script setup lang="ts">
import { apiUrls } from "@/api";
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import JobDispatchList from "@/components/Modules/Audits/JobDispatches/JobDispatchList";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import TaskProcessAgentThreadCard from "@/components/Modules/WorkflowCanvas/TaskProcessAgentThreadCard";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { useHashedColor } from "@/composables/useHashedColor";
import { TaskProcess } from "@/types";
import { FaSolidClockRotateLeft } from "danx-icon";
import { QSkeleton, QSpinnerGears } from "quasar";
import { fDateTime, InfoDialog, LabelPillWidget, request, ShowHideButton } from "quasar-ui-danx";
import { onMounted, reactive, ref } from "vue";

const props = defineProps<{
    taskProcess: TaskProcess;
    isShowing?: boolean;
}>();

defineEmits<{
    close: [];
}>();

const isLoading = ref(false);
const historicalProcesses = ref<TaskProcess[]>([]);
const expandedSections = reactive<Record<string, boolean>>({});
const loadingArtifacts = reactive<Record<number, boolean>>({});
const loadingJobDispatches = reactive<Record<number, boolean>>({});

function getOperationColor(operation: string | undefined) {
    return useHashedColor(ref(operation)).value;
}

async function loadHistory() {
    isLoading.value = true;
    try {
        const response = await request.get(apiUrls.tasks.processHistory({ id: props.taskProcess.id }));
        historicalProcesses.value = response.data || [];
    } catch (error) {
        console.error("Failed to load process history:", error);
        historicalProcesses.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function loadProcessArtifacts(process: TaskProcess) {
    if (process.outputArtifacts || loadingArtifacts[process.id]) return;

    loadingArtifacts[process.id] = true;
    try {
        const result = await dxTaskProcess.routes.details(process, { outputArtifacts: true }, { params: { withTrashed: true } });
        // Update the local process object since historicalProcesses is not managed by the danx store
        if (result?.outputArtifacts) {
            process.outputArtifacts = result.outputArtifacts;
        }
    } finally {
        loadingArtifacts[process.id] = false;
    }
}

async function loadProcessJobDispatches(process: TaskProcess) {
    if (process.jobDispatches || loadingJobDispatches[process.id]) return;

    loadingJobDispatches[process.id] = true;
    try {
        const result = await dxTaskProcess.routes.details(process, { jobDispatches: { logs: true } }, { params: { withTrashed: true } });
        // Update the local process object since historicalProcesses is not managed by the danx store
        if (result?.jobDispatches) {
            process.jobDispatches = result.jobDispatches;
        }
    } finally {
        loadingJobDispatches[process.id] = false;
    }
}

onMounted(() => {
    if (props.isShowing) {
        loadHistory();
    }
});
</script>
