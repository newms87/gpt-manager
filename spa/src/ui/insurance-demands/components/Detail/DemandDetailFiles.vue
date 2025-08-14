<template>
  <UiCard>
    <template #header>
      <h3 class="text-lg font-semibold text-slate-800">
        Documents
      </h3>
    </template>

    <MultiFileField
      :model-value="files"
      :readonly="demand?.status !== DEMAND_STATUS.DRAFT"
      :disabled="demand?.status !== DEMAND_STATUS.DRAFT"
      :width="70"
      :height="60"
      add-icon-class="w-5"
      show-transcodes
      file-preview-class="rounded-lg"
      file-preview-btn-size="xs"
      @update:model-value="$emit('update:files', $event)"
    />
  </UiCard>
</template>

<script setup lang="ts">
import { MultiFileField } from "quasar-ui-danx";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";

defineProps<{
  demand: UiDemand | null;
  files: any[];
}>();

defineEmits<{
  'update:files': [files: any[]];
}>();
</script>