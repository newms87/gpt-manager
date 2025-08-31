<template>
    <ActionTable
        class="flex-grow"
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
</template>

<script setup lang="ts">
import { dxUsageEvent } from "@/components/Modules/UsageEvents";
import { ActionTable } from "quasar-ui-danx";
import { onMounted } from "vue";

const props = defineProps<{
    filter: Record<string, any>;
}>();

onMounted(() => {
    dxUsageEvent.initialize();
});
</script>
