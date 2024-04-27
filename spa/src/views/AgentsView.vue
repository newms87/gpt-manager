<template>
    <ActionTableLayout
        v-model:filter="filter"
        v-model:selected-rows="selectedRows"
        v-model:pagination="quasarPagination"
        v-model:active-panel="activePanel"
        v-model:active-item="activeItem"
        label="Agents"
        name="agents"
        table-class="bg-slate-600"
        show-filters
        refresh-button
        :filter-fields="filterFields"
        :batch-actions="filterActions({ batch: true })"
        :exporter="() => Agents.download(filter)"
        :summary="summary"
        :loading-list="isLoadingList"
        :loading-summary="isLoadingSummary"
        :paged-items="pagedItems"
        :columns="columns"
        :panels="panels"
        @refresh="refreshAll"
    />
</template>
<script setup>
import { filterActions } from "@/components/Agents/agentsActions";
import { columns } from "@/components/Agents/agentsColumns";
import {
    activeItem,
    activePanel,
    filter,
    initialize,
    isLoadingList,
    isLoadingSummary,
    pagedItems,
    quasarPagination,
    refreshAll,
    selectedRows,
    summary
} from "@/components/Agents/agentsControls";
import { filterFields } from "@/components/Agents/agentsFilters";
import { panels } from "@/components/Agents/agentsPanels";
import { Agents } from "@/routes/agents";
import { ActionTableLayout } from "quasar-ui-danx";

initialize();
</script>
