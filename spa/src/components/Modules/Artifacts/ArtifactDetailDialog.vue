<template>
    <QDialog :model-value="!!navigationStack.length" maximized @update:model-value="onDialogClose">
        <div class="flex items-center justify-center w-full h-full" @click.self="closeDialog">
            <div
                style="width: 90vw; height: 90vh;"
                :class="themeClass('bg-slate-800', 'bg-white')"
                class="max-w-6xl rounded-lg overflow-hidden flex flex-col shadow-2xl"
            >
                <!-- Dialog Header with Breadcrumb and Tabs -->
                <div :class="themeClass('bg-slate-900 border-slate-700', 'bg-slate-100 border-slate-200')" class="border-b">
                    <!-- Breadcrumb Navigation -->
                    <div class="flex items-center justify-between px-4 py-2">
                        <div class="flex items-center gap-1 min-w-0 flex-1 overflow-hidden">
                            <template v-for="(item, index) in navigationStack" :key="item.id">
                                <!-- Separator -->
                                <ChevronRightIcon
                                    v-if="index > 0"
                                    :class="themeClass('text-slate-500', 'text-slate-400')"
                                    class="w-3 h-3 flex-shrink-0"
                                />
                                <!-- Breadcrumb item -->
                                <button
                                    v-if="index < navigationStack.length - 1"
                                    :class="themeClass('text-sky-400 hover:text-sky-300', 'text-sky-600 hover:text-sky-500')"
                                    class="text-sm font-medium truncate max-w-48 hover:underline"
                                    :title="`${item.name} (#${item.id})`"
                                    @click="navigateToIndex(index)"
                                >
                                    {{ item.name }} (#{{ item.id }})
                                </button>
                                <!-- Current item (not clickable) -->
                                <span
                                    v-else
                                    :class="themeClass('text-slate-200', 'text-slate-800')"
                                    class="text-sm font-semibold truncate max-w-64"
                                    :title="`${item.name} (#${item.id})`"
                                >
                                    {{ item.name }} (#{{ item.id }})
                                </span>
                            </template>
                        </div>
                        <ActionButton
                            type="close"
                            :color="themeClass('slate-invert', 'slate-soft')"
                            size="sm"
                            tooltip="Close"
                            @click="closeDialog"
                        />
                    </div>
                    <!-- Tabs -->
                    <QTabs
                        v-model="activeContentTab"
                        :class="themeClass('text-slate-300', 'text-slate-700')"
                        active-color="primary"
                        indicator-color="primary"
                        align="left"
                        dense
                    >
                        <QTab v-if="hasText" name="text">
                            <div class="flex items-center gap-2">
                                <TextIcon class="w-4 text-green-500" />
                                <span>Text</span>
                                <span class="text-slate-400">{{ textCount }}</span>
                            </div>
                        </QTab>
                        <QTab v-if="hasFiles" name="files">
                            <div class="flex items-center gap-2">
                                <FilesIcon class="w-4 text-orange-500" />
                                <span>Files</span>
                                <span class="text-slate-400">{{ filesCount }}</span>
                            </div>
                        </QTab>
                        <QTab v-if="hasJson" name="json">
                            <div class="flex items-center gap-2">
                                <JsonIcon class="w-4 text-purple-500" />
                                <span>JSON</span>
                                <span class="text-slate-400">{{ jsonCount }}</span>
                            </div>
                        </QTab>
                        <QTab v-if="hasMeta" name="meta">
                            <div class="flex items-center gap-2">
                                <MetaIcon class="w-4 text-slate-400" />
                                <span>Meta</span>
                                <span class="text-slate-400">{{ metaCount }}</span>
                            </div>
                        </QTab>
                        <QTab v-if="hasGroup" name="children">
                            <div class="flex items-center gap-2">
                                <GroupIcon class="w-4 text-blue-500" />
                                <span>Children</span>
                                <span class="text-slate-400">{{ childrenCount }}</span>
                            </div>
                        </QTab>
                    </QTabs>
                </div>

                <!-- Tab Panels -->
                <QTabPanels
                    v-model="activeContentTab"
                    :class="themeClass('bg-slate-800', 'bg-white')"
                    class="flex-1 overflow-y-auto"
                    animated
                >
                    <!-- Text Panel -->
                    <QTabPanel v-if="hasText" name="text" class="p-4">
                        <pre :class="themeClass('text-slate-300 bg-slate-900', 'text-slate-700 bg-slate-50')" class="text-sm whitespace-pre-wrap p-4 rounded overflow-y-auto">{{ currentArtifact.text_content }}</pre>
                    </QTabPanel>

                    <!-- Files Panel -->
                    <QTabPanel v-if="hasFiles" name="files" class="p-4">
                        <div class="flex items-stretch justify-start flex-wrap gap-4">
                            <div
                                v-for="file in currentArtifact.files"
                                :key="'file-upload-' + file.id"
                                class="flex flex-col items-center"
                            >
                                <FilePreview
                                    class="cursor-pointer bg-gray-200 w-32 h-32"
                                    :file="file"
                                    :related-files="currentArtifact.files"
                                    downloadable
                                />
                                <div
                                    :class="themeClass('text-gray-400', 'text-gray-500')"
                                    class="text-xs mt-2 w-32 text-center truncate"
                                    :title="(file.page_number) + '. ' + file.filename"
                                >
                                    {{ file.page_number }}. {{ file.filename }}
                                </div>
                            </div>
                        </div>
                    </QTabPanel>

                    <!-- JSON Panel -->
                    <QTabPanel v-if="hasJson" name="json" class="p-4">
                        <CodeViewer
                            :model-value="currentArtifact.json_content"
                            format="yaml"
                        />
                    </QTabPanel>

                    <!-- Meta Panel -->
                    <QTabPanel v-if="hasMeta" name="meta" class="p-4">
                        <CodeViewer
                            :model-value="currentArtifact.meta"
                            format="yaml"
                        />
                    </QTabPanel>

                    <!-- Children Panel -->
                    <QTabPanel v-if="hasGroup" name="children" class="p-4">
                        <ChildArtifactList
                            :parent-artifact-id="currentArtifact.id"
                            @navigate="navigateToChild"
                        />
                    </QTabPanel>
                </QTabPanels>
            </div>
        </div>
    </QDialog>
</template>

<script setup lang="ts">
import ChildArtifactList from "@/components/Modules/Artifacts/ChildArtifactList.vue";
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import { useStoredFileUpdates } from "@/composables/useStoredFileUpdates";
import { Artifact } from "@/types";
import {
    FaSolidChevronRight as ChevronRightIcon,
    FaSolidCircleInfo as MetaIcon,
    FaSolidCode as JsonIcon,
    FaSolidFileLines as TextIcon,
    FaSolidFolder as FilesIcon,
    FaSolidLayerGroup as GroupIcon
} from "danx-icon";
import { ActionButton, CodeViewer, FilePreview } from "quasar-ui-danx";
import { QDialog, QTab, QTabPanel, QTabPanels, QTabs } from "quasar";
import { computed, ref, watch } from "vue";

type ContentTab = "text" | "files" | "json" | "meta" | "children";

const { themeClass } = useAuditCardTheme();

// Navigation stack - array of artifacts being viewed
const navigationStack = ref<Artifact[]>([]);

// The currently displayed artifact (top of the stack)
const currentArtifact = computed(() => navigationStack.value[navigationStack.value.length - 1] || null);

// Active tab state
const activeContentTab = ref<ContentTab>("text");

// Content type checks for current artifact
const hasText = computed(() => !!currentArtifact.value?.text_content);
const hasFiles = computed(() => !!currentArtifact.value?.files?.length);
const hasJson = computed(() => !!currentArtifact.value?.json_content);
const hasMeta = computed(() => !!currentArtifact.value?.meta);
const hasGroup = computed(() => (currentArtifact.value?.child_artifacts_count || 0) > 0);

// Content counts for current artifact
const textCount = computed(() => {
    if (!currentArtifact.value?.text_content) return 0;
    return currentArtifact.value.text_content.length;
});

const filesCount = computed(() => currentArtifact.value?.files?.length || 0);

const jsonCount = computed(() => {
    if (!currentArtifact.value?.json_content) return 0;
    if (Array.isArray(currentArtifact.value.json_content)) {
        return currentArtifact.value.json_content.length;
    }
    return Object.keys(currentArtifact.value.json_content).length;
});

const metaCount = computed(() => {
    if (!currentArtifact.value?.meta) return 0;
    if (Array.isArray(currentArtifact.value.meta)) {
        return currentArtifact.value.meta.length;
    }
    return Object.keys(currentArtifact.value.meta).length;
});

const childrenCount = computed(() => currentArtifact.value?.child_artifacts_count || 0);

/**
 * Open the dialog with an artifact and optional initial tab
 */
function open(artifact: Artifact, tab?: ContentTab) {
    navigationStack.value = [artifact];
    activeContentTab.value = tab || getDefaultTab(artifact);
}

/**
 * Navigate to a child artifact (push onto stack), optionally opening a specific tab
 */
function navigateToChild(artifact: Artifact, tab?: ContentTab) {
    navigationStack.value = [...navigationStack.value, artifact];
    activeContentTab.value = tab || getDefaultTab(artifact);
}

/**
 * Navigate back to a specific index in the breadcrumb
 */
function navigateToIndex(index: number) {
    navigationStack.value = navigationStack.value.slice(0, index + 1);
    activeContentTab.value = getDefaultTab(currentArtifact.value);
}

/**
 * Get the default tab for an artifact based on available content
 */
function getDefaultTab(artifact: Artifact | null): ContentTab {
    if (!artifact) return "text";
    if (artifact.text_content) return "text";
    if (artifact.files?.length) return "files";
    if (artifact.json_content) return "json";
    if (artifact.meta) return "meta";
    if ((artifact.child_artifacts_count || 0) > 0) return "children";
    return "text";
}

/**
 * Close the dialog
 */
function closeDialog() {
    navigationStack.value = [];
}

/**
 * Handle dialog close event from QDialog
 */
function onDialogClose(value: boolean) {
    if (!value) {
        closeDialog();
    }
}

// Subscribe to file updates for real-time transcoding progress
const { subscribeToFileUpdates, unsubscribeFromFileUpdates } = useStoredFileUpdates();

// Subscribe to files when dialog is showing files tab
watch([currentArtifact, activeContentTab], ([artifact, tab], [oldArtifact]) => {
    // Unsubscribe from old artifact's files
    if (oldArtifact?.files?.length > 0) {
        oldArtifact.files.forEach(file => unsubscribeFromFileUpdates(file));
    }

    // Subscribe to new artifact's files if viewing files tab
    if (artifact && tab === "files" && artifact.files?.length > 0) {
        artifact.files.forEach(file => subscribeToFileUpdates(file));
    }
});

// Expose methods for external use
defineExpose({
    open
});
</script>
