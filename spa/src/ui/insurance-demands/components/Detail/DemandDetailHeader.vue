<template>
  <div class="px-6 py-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">
          {{ demand?.title || "Loading..." }}
        </h1>
        <div v-if="demand?.status" class="flex items-center space-x-4 mt-1">
          <UiStatusBadge :status="demand.status" />
          <span v-if="demand.created_at" class="text-sm text-slate-500">
            Created {{ formatDate(demand.created_at) }}
          </span>
        </div>
      </div>

      <div class="flex items-center space-x-3">
        <slot name="actions" />

        <!-- Complete / Set As Draft Button -->
        <ActionButton
          v-if="demand && demand.status !== DEMAND_STATUS.COMPLETED"
          type="check"
          color="green"
          size="sm"
          tooltip="Mark Complete"
          :saving="isCompletingOrSettingDraft"
          @click="handleMarkComplete"
        />

        <ActionButton
          v-if="demand && demand.status === DEMAND_STATUS.COMPLETED"
          type="clock"
          color="slate"
          size="sm"
          tooltip="Set As Draft"
          :saving="isCompletingOrSettingDraft"
          @click="handleSetAsDraft"
        />

        <!-- Delete Button -->
        <ActionButton
          v-if="demand"
          type="trash"
          color="red"
          size="sm"
          tooltip="Delete Demand"
          :saving="isDeleting"
          @click="handleDelete"
        />

        <RouterLink to="/ui/demands">
          <ActionButton
            type="back"
            size="sm"
            label="View All Demands"
          />
        </RouterLink>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-if="showDeleteConfirm"
      class="ui-mode"
      title="Delete Demand?"
      content="Are you sure you want to delete this demand? This action cannot be undone."
      color="negative"
      :is-saving="isDeleting"
      @confirm="confirmDelete"
      @close="showDeleteConfirm = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { ref } from "vue";
import { RouterLink } from "vue-router";
import { UiStatusBadge } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";

const props = defineProps<{
  demand: UiDemand | null;
}>();

const emit = defineEmits<{
  "mark-complete": [];
  "set-draft": [];
  "delete": [];
}>();

const isCompletingOrSettingDraft = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit"
  });
};

const handleMarkComplete = () => {
  emit("mark-complete");
};

const handleSetAsDraft = () => {
  emit("set-draft");
};

const handleDelete = () => {
  showDeleteConfirm.value = true;
};

const confirmDelete = () => {
  emit("delete");
  showDeleteConfirm.value = false;
};

// Expose loading state setters so parent can control them
defineExpose({
  setCompletingOrSettingDraft: (value: boolean) => {
    isCompletingOrSettingDraft.value = value;
  },
  setDeleting: (value: boolean) => {
    isDeleting.value = value;
  }
});
</script>