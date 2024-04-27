<template>
    <ActionTableLayout>
        <template #toolbar>
            <AgentsToolbar />
        </template>
        <template #filters>
            <CollapsableFiltersSidebar
                v-model:filter="filter"
                show-filters
                name="agents-sidebar"
                :filter-fields="filterFields"
            />
        </template>
        <AgentsTable class="bg-slate-600" />
        <PanelsDrawer
            v-if="activeItem"
            v-model="activePanel"
            :panels="panels"
            @close="activeItem = null"
        />
    </ActionTableLayout>
</template>
<script setup>
import { activeItem, activePanel, filter, initialize } from "@/components/Agents/agentsControls";
import { filterFields } from "@/components/Agents/agentsFilters";
import { panels } from "@/components/Agents/agentsPanels";
import AgentsTable from "@/components/Agents/AgentsTable";
import AgentsToolbar from "@/components/Agents/AgentsToolbar";
import { ActionTableLayout, CollapsableFiltersSidebar, PanelsDrawer } from "quasar-ui-danx";

initialize();
</script>
