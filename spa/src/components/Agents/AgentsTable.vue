<template>
    <ActionTableLayout
        :controller="AgentController"
        table-class="bg-slate-600"
        show-filters
        refresh-button
        :filters="filters"
        :actions="actions"
        :columns="columns"
        :panels="panels"
    />
</template>
<script setup>
import { actions } from "@/components/Agents/agentsActions";
import { columns } from "@/components/Agents/agentsColumns";
import { AgentController } from "@/components/Agents/agentsControls";
import { filters } from "@/components/Agents/agentsFilters";
import { panels } from "@/components/Agents/agentsPanels";
import { ActionTableLayout } from "quasar-ui-danx";

AgentController.initialize();
</script>
