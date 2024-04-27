<template>
    <ActionTableLayout>
        <template #toolbar>
            <ActionToolbar
                refresh
                :actions="filterActions({ batch: true })"
                :action-target="selectedRows"
                :exporter="() => Agents.download(filter)"
                @refresh="refreshAll"
            />
        </template>
        <template #filters>
            <CollapsableFiltersSidebar
                v-model:filter="filter"
                show-filters
                name="agents-sidebar"
                :filter-fields="filterFields"
            />
        </template>
        <ActionTable
            v-model:selected-rows="selectedRows"
            v-model:quasar-pagination="quasarPagination"
            label="Agents"
            name="agents"
            class="bg-slate-600"
            :summary="summary"
            :is-loading-list="isLoadingList"
            :is-loading-summary="isLoadingSummary"
            :paged-items="pagedItems"
            :columns="columns"
        />
        <PanelsDrawer
            v-if="activeItem"
            v-model="activePanel"
            :panels="panels"
            @close="activeItem = null"
        />
    </ActionTableLayout>
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
import { ActionTable, ActionTableLayout, ActionToolbar, CollapsableFiltersSidebar, PanelsDrawer } from "quasar-ui-danx";

initialize();
</script>
