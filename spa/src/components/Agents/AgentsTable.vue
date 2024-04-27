<template>
    <ActionTable
        v-model:selected-rows="selectedRows"
        v-model:quasar-pagination="quasarPagination"
        label="Agents"
        name="agents"
        color="slate-600"
        :summary="summary"
        :is-loading-list="isLoadingList"
        :is-loading-summary="isLoadingSummary"
        :paged-items="pagedItems"
        :columns="columns"
    />
</template>
<script setup>
import { columns } from "@/components/Agents/agentsColumns";
import {
    isLoadingList,
    isLoadingSummary,
    pagedItems,
    quasarPagination,
    selectedRows,
    summary
} from "@/components/Agents/agentsControls";
import { ActionTable } from "quasar-ui-danx";
</script>

