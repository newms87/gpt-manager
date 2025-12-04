<template>
  <div class="rounded-lg p-6" :class="sectionClasses">
    <!-- Section Header -->
    <div class="flex items-center space-x-3 mb-4">
      <h3 :class="titleClasses">{{ section.section_title }}</h3>
      <span :class="countClasses">{{ section.artifacts.length }}</span>
    </div>

    <!-- Files display type -->
    <template v-if="section.display_type === 'files'">
      <div v-if="artifactFiles.length === 0" class="text-slate-400 text-center py-4">
        No files available
      </div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        <div
          v-for="file in artifactFiles"
          :key="'file-' + file.id"
          class="flex flex-col items-center"
        >
          <FilePreview
            class="cursor-pointer bg-gray-200 w-full h-32"
            :file="file"
            :related-files="artifactFiles"
            downloadable
            :removable="section.deletable"
            @remove="handleDeleteFile(file)"
          />
          <div
            class="text-xs text-gray-400 mt-1 w-full text-center truncate"
            :title="file.filename"
          >
            {{ file.filename }}
          </div>
        </div>
      </div>
    </template>

    <!-- Artifacts display type (default) -->
    <template v-else>
      <div v-if="section.artifacts.length === 0" class="text-slate-400 text-center py-4">
        No artifacts available
      </div>
      <ListTransition v-else>
        <ArtifactItem
          v-for="artifact in section.artifacts"
          :key="artifact.id"
          :artifact="artifact"
          :editable="section.editable"
          :deletable="section.deletable"
          :color="section.color"
        />
      </ListTransition>
    </template>
  </div>
</template>

<script setup lang="ts">
import ArtifactItem from "@/ui/insurance-demands/components/Detail/ArtifactItem.vue";
import { FilePreview, ListTransition, StoredFile } from "quasar-ui-danx";
import { computed } from "vue";

interface Artifact {
  id: number;
  name: string;
  text_content?: string;
  json_content?: any;
  meta?: any;
  files?: StoredFile[];
  created_at: string;
}

interface ArtifactSection {
  workflow_key: string;
  section_title: string;
  artifact_category: string;
  display_type: 'artifacts' | 'files';
  editable: boolean;
  deletable: boolean;
  artifacts: Artifact[];
  color: string;
}

const props = defineProps<{
  section: ArtifactSection;
}>();

const emit = defineEmits<{
  'delete-file': [file: StoredFile];
}>();

// Extract all files from artifacts for files display mode
const artifactFiles = computed(() => {
  const files: StoredFile[] = [];
  for (const artifact of props.section.artifacts) {
    if (artifact.files?.length) {
      files.push(...artifact.files);
    }
  }
  return files;
});

// Dynamic styling based on section color
const sectionClasses = computed(() => {
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-950 border-l-4 border-blue-500',
    teal: 'bg-teal-950 border-l-4 border-teal-500',
    green: 'bg-green-950 border-l-4 border-green-500',
    purple: 'bg-purple-950 border-l-4 border-purple-500',
    slate: 'bg-slate-900 border-l-4 border-slate-500',
    sky: 'bg-sky-950 border-l-4 border-sky-500'
  };

  return colorMap[props.section.color] || colorMap.slate;
});

const titleClasses = computed(() => {
  const colorMap: Record<string, string> = {
    blue: 'text-blue-200',
    teal: 'text-teal-200',
    green: 'text-green-200',
    purple: 'text-purple-200',
    slate: 'text-slate-200',
    sky: 'text-sky-200'
  };

  return ['text-xl font-semibold', colorMap[props.section.color] || colorMap.slate];
});

const countClasses = computed(() => {
  const colorMap: Record<string, string> = {
    blue: 'bg-blue-800 text-blue-200',
    teal: 'bg-teal-800 text-teal-200',
    green: 'bg-green-800 text-green-200',
    purple: 'bg-purple-800 text-purple-200',
    slate: 'bg-slate-800 text-slate-200',
    sky: 'bg-sky-800 text-sky-200'
  };

  return ['px-2 py-1 rounded-full text-sm font-medium', colorMap[props.section.color] || colorMap.slate];
});

function handleDeleteFile(file: StoredFile) {
  emit('delete-file', file);
}
</script>
