<template>
    <div class="agent-config-field">
        <SelectionMenuField
            v-model:editing="isEditingAgent"
            v-model:selected="agent"
            selectable
            editable
            deletable
            name-editable
            creatable
            class="flex-grow"
            :select-icon="AgentIcon"
            select-text="Agent"
            label-class="text-slate-300"
            :options="availableAgents"
            :loading="isLoading"
            @create="createAgentAction.trigger(null, {name: newAgentName})"
            @update:editing="isTrue => isTrue ? loadAgentDetails(agent) : null"
            @update="input => updateAgentAction.trigger(agent, input)"
            @delete="onDeleteAgent"
        />
        <div v-if="isEditingAgent" class="mt-4 bg-slate-800 rounded p-8">
            <QSkeleton v-if="agent?.id === agentToLoadDetails?.id" class="h-16" />
            <template v-else>
                <AgentApiConfigForm v-model:agent="agent" />
            </template>
        </div>
    </div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import AgentApiConfigForm from "@/components/Modules/Agents/Forms/AgentApiConfigForm";
import {
    agentToLoadDetails,
    availableAgents,
    isLoadingAgents,
    loadAgentDetails,
    loadAgents
} from "@/components/Modules/Agents/store";
import { Agent } from "@/types";
import { FaSolidRobot as AgentIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { computed, ref } from "vue";

withDefaults(defineProps<{
    newAgentName?: string;
}>(), {
    newAgentName: "My New Agent"
});
const agent = defineModel<Agent | null>();
const isEditingAgent = ref(false);
const createAgentAction = dxAgent.getAction("create", {
    onFinish: async (results) => {
        await loadAgents();
        agent.value = results.item;
    }
});
const updateAgentAction = dxAgent.getAction("update");
const deleteAgentAction = dxAgent.getAction("delete-with-confirm");

const isLoading = computed(() => createAgentAction.isApplying || updateAgentAction.isApplying || deleteAgentAction.isApplying || isLoadingAgents.value);

async function onDeleteAgent(deletedAgent: Agent) {
    const result = await deleteAgentAction.trigger(deletedAgent);
    if (result) {
        isEditingAgent.value = false;
        if (agent.value?.id === deletedAgent.id) {
            agent.value = null;
        }
        await loadAgents();
    }
}

// Immediately load agents
loadAgents();
</script>
