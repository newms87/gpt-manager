<template>
    <div class="flex items-center">
        <div class="flex-grow px-6">
            <h2>Agents</h2>
        </div>
        <div class="py-3 px-6 flex items-center flex-nowrap">
            <RefreshButton :loading="isLoadingList" class="bg-slate-600" @click="refreshAll" />
            <ExportButton
                :exporter="() => Agents.download(filter)"
                class="ml-4 bg-slate-600"
            />
            <ActionMenu
                class="ml-4 px-4 bg-slate-600 rounded"
                :target="selectedRows"
                :actions="filterActions({ batch: true })"
            />
        </div>
    </div>
</template>
<script setup>
import { filterActions } from "@/components/Agents/agentsActions";
import { filter, isLoadingList, refreshAll, selectedRows } from "@/components/Agents/agentsControls";
import { Agents } from "@/routes/agents";
import { ActionMenu, ExportButton, RefreshButton } from "quasar-ui-danx";
</script>
