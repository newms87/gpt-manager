<template>
    <ActionTable
        class="dx-action-table-theme-light"
        :pagination="dxUsageEvent.pagination.value"
        :selected-rows="dxUsageEvent.selectedRows.value"
        :label="dxUsageEvent.label"
        :name="dxUsageEvent.name"
        :summary="dxUsageEvent.summary.value"
        :loading-list="dxUsageEvent.isLoadingList.value"
        :loading-summary="dxUsageEvent.isLoadingSummary.value"
        :paged-items="dxUsageEvent.pagedItems.value"
        :columns="dxUsageEvent.columns || []"
        @update:selected-rows="dxUsageEvent.setSelectedRows"
        @update:pagination="dxUsageEvent.setPagination"
    />

    <UsageEventMetadataModal
        :visible="dxUsageEvent.showMetadataModal.value"
        :event="dxUsageEvent.selectedEventForMetadata.value"
        @close="dxUsageEvent.hideMetadata"
    />
</template>

<script setup lang="ts">
import { dxUsageEvent } from "@/components/Modules/UsageEvents";
import { ActionTable } from "quasar-ui-danx";
import { onMounted } from "vue";
import UsageEventMetadataModal from "./UsageEventMetadataModal.vue";

const props = defineProps<{
    filter: Record<string, any>;
}>();

onMounted(() => {
    dxUsageEvent.initialize();
    dxUsageEvent.setActiveFilter(props.filter);
});
</script>
