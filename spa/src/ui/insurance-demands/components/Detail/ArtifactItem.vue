<template>
  <div class="rounded-lg p-4 mb-3" :class="containerClasses">
    <!-- Header Row -->
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center space-x-2 flex-grow overflow-hidden">
        <div class="font-medium text-slate-200 truncate">{{ artifact.name }}</div>
        <LabelPillWidget :label="fDateTime(artifact.created_at)" color="blue" size="xs" class="flex-shrink-0" />
      </div>

      <div class="flex items-center space-x-1 flex-shrink-0">
        <!-- Toggle buttons for different content types -->
        <ShowHideButton
          v-if="hasText"
          v-model="isShowingText"
          :show-icon="TextIcon"
          class="bg-green-900"
          size="xs"
          tooltip="Show Text Content"
        />
        <ShowHideButton
          v-if="hasJson"
          v-model="isShowingJson"
          :show-icon="JsonIcon"
          class="bg-purple-700"
          size="xs"
          tooltip="Show JSON Content"
        />
        <ShowHideButton
          v-if="hasMeta"
          v-model="isShowingMeta"
          :show-icon="MetaIcon"
          class="bg-slate-500 text-slate-300"
          size="xs"
          tooltip="Show Meta"
        />
        <ShowHideButton
          v-if="hasFiles"
          v-model="isShowingFiles"
          :show-icon="FilesIcon"
          class="bg-amber-900"
          size="xs"
          tooltip="Show Files"
        />

        <!-- Action buttons -->
        <ActionButton
          v-if="deletable"
          type="trash"
          color="red"
          size="xs"
          tooltip="Delete"
          :action="deleteAction"
          :target="artifact"
        />
      </div>
    </div>

    <!-- Expandable Content -->
    <ListTransition>
      <!-- Text Content -->
      <div v-if="hasText && isShowingText" class="mt-3">
        <div class="text-xs text-slate-400 mb-1">Text Content</div>
        <MarkdownEditor
          v-model="editedTextContent"
          format="text"
          :readonly="!editable"
          class="bg-slate-800"
        />
        <div v-if="hasChanges" class="flex justify-end space-x-2 mt-2">
          <ActionButton
            type="cancel"
            label="Cancel"
            color="slate"
            size="sm"
            @click="cancelEdit"
          />
          <ActionButton
            type="save"
            label="Save"
            color="blue"
            size="sm"
            :loading="isSaving"
            @click="saveEdit"
          />
        </div>
      </div>

      <!-- JSON Content -->
      <div v-if="hasJson && isShowingJson" class="mt-3">
        <div class="text-xs text-slate-400 mb-1">JSON Content</div>
        <CodeViewer
          :model-value="artifact.json_content"
          format="yaml"
        />
      </div>

      <!-- Meta -->
      <div v-if="hasMeta && isShowingMeta" class="mt-3">
        <div class="text-xs text-slate-400 mb-1">Meta</div>
        <CodeViewer
          :model-value="artifact.meta"
          format="yaml"
        />
      </div>

      <!-- Files -->
      <div v-if="hasFiles && isShowingFiles" class="mt-3">
        <div class="text-xs text-slate-400 mb-1">Files ({{ artifact.files.length }})</div>
        <div class="flex items-stretch justify-start flex-wrap gap-2 mt-2">
          <div
            v-for="file in artifact.files"
            :key="'file-' + file.id"
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
              :title="file.filename"
            >
              {{ file.filename }}
            </div>
          </div>
        </div>
      </div>
    </ListTransition>
  </div>
</template>

<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import {
  FaSolidBarcode as MetaIcon,
  FaSolidDatabase as JsonIcon,
  FaSolidFile as FilesIcon,
  FaSolidT as TextIcon
} from "danx-icon";
import { ActionButton, CodeViewer, fDateTime, FilePreview, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import { useDemandArtifacts } from "../../composables";
import { getWorkflowColors } from "../../config";

interface Artifact {
  id: number;
  name: string;
  text_content?: string;
  json_content?: any;
  meta?: any;
  files?: any[];
  created_at: string;
}

const props = withDefaults(defineProps<{
  artifact: Artifact;
  editable?: boolean;
  deletable?: boolean;
  color?: string;
}>(), {
  editable: false,
  deletable: false,
  color: 'slate'
});

// Get actions from dxArtifact controller
const { reloadArtifactSections } = useDemandArtifacts();
const updateAction = dxArtifact.getAction("update");
const deleteAction = dxArtifact.getAction("delete-with-confirm", { onFinish: reloadArtifactSections });

// Get color palette
const colors = computed(() => getWorkflowColors(props.color));

// Content type checks
const hasText = computed(() => !!props.artifact.text_content);
const hasJson = computed(() => !!props.artifact.json_content);
const hasMeta = computed(() => !!props.artifact.meta);
const hasFiles = computed(() => !!props.artifact.files?.length);

// Visibility state - all collapsed by default
const isShowingText = ref(false);
const isShowingJson = ref(false);
const isShowingMeta = ref(false);
const isShowingFiles = ref(false);

// Edit state
const isSaving = ref(false);
const editedTextContent = ref(props.artifact.text_content || '');

// Track if there are unsaved changes
const hasChanges = computed(() => editedTextContent.value !== (props.artifact.text_content || ''));

// Sync editedTextContent when artifact changes (e.g., after save)
watch(() => props.artifact.text_content, (newContent) => {
  editedTextContent.value = newContent || '';
});

// Dynamic styling using the palette
const containerClasses = computed(() => ['border-l-4', colors.value.artifactClasses]);

function cancelEdit() {
  // Reset to original content
  editedTextContent.value = props.artifact.text_content || '';
}

async function saveEdit() {
  isSaving.value = true;
  try {
    await updateAction.trigger(props.artifact, { text_content: editedTextContent.value });
  } finally {
    isSaving.value = false;
  }
}
</script>
