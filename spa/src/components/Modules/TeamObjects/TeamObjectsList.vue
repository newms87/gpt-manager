<template>
    <div>
        <div v-if="teamObjectType" class="mt-4">
            <ActionButton
                type="create"
                color="green"
                size="sm"
                :label="teamObjectType"
                :action="createTeamObjectAction"
                :input="{ type: teamObjectType, schema_definition_id: schemaDefinition.id }"
            />
        </div>
        <QBanner v-else-if="schemaDefinition.can?.view === false" class="bg-yellow-800 text-slate-300 mt-8">
            You are not allowed to view this schema
        </QBanner>
        <QBanner v-else class="bg-red-800 text-slate-300 mt-8">
            Please update the schema to include the title property at the top level
        </QBanner>

        <template v-if="dxTeamObject.isLoadingList.value && !teamObjects?.length">
            <QSkeleton
                v-for="i in 3"
                :key="i"
                class="mt-4"
                height="5em"
            />
        </template>
        <ListTransition v-else-if="teamObjects?.length > 0">
            <TeamObjectCard
                v-for="teamObject in teamObjects"
                :key="teamObject.id"
                :object="teamObject"
                :schema="schemaDefinition.schema || {} as JsonSchema"
                class="mt-4 bg-slate-800 rounded"
                @select="dxTeamObject.activatePanel(teamObject, 'workflows')"
                @merge="showMergeDialog(teamObject)"
            />
        </ListTransition>

        <PanelsDrawer
            v-if="activeTeamObject"
            :title="activeTeamObject.name"
            :model-value="activePanel"
            :target="activeTeamObject"
            :panels="dxTeamObject.panels"
            @update:model-value="panel => dxTeamObject.activatePanel(activeTeamObject, panel)"
            @close="dxTeamObject.setActiveItem(null)"
        />

        <TeamObjectMergeDialog
            v-model="showMergeDialogRef"
            :source-object="sourceTeamObject"
            :available-objects="teamObjects || []"
            @merge="performMerge"
        />
    </div>
</template>
<script setup lang="ts">
import { dxTeamObject, TeamObjectCard } from "@/components/Modules/TeamObjects";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectMergeDialog from "@/components/Modules/TeamObjects/TeamObjectMergeDialog.vue";
import { JsonSchema, SchemaDefinition } from "@/types";
import { apiUrls } from "@/api";
import { ActionButton, ListTransition, PanelsDrawer, request } from "quasar-ui-danx";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps<{ schemaDefinition: SchemaDefinition }>();

onMounted(init);
watch(() => props.schemaDefinition, loadTeamObjects);

const createTeamObjectAction = dxTeamObject.getAction("create-with-name");
const teamObjectType = computed(() => props.schemaDefinition?.schema?.title);
const teamObjects = computed(() => dxTeamObject.pagedItems.value?.data);
const activeTeamObject = computed(() => dxTeamObject.activeItem.value);
const activePanel = ref("workflows");

const showMergeDialogRef = ref(false);
const sourceTeamObject = ref<TeamObject | null>(null);

async function init() {
    dxTeamObject.setActiveFilter({
        schema_definition_id: props.schemaDefinition.id,
        type: teamObjectType.value
    });

    dxTeamObject.initialize({
        isDetailsEnabled: false,
        isListEnabled: false,
        isSummaryEnabled: false,
        isFieldOptionsEnabled: false
    });

    await loadTeamObjects();
}

async function loadTeamObjects() {
    // If the team object type is not set, do not load any team objects and clear the current results
    if (!teamObjectType.value) {
        dxTeamObject.pagedItems.value = null;
        dxTeamObject.setOptions({ isListEnabled: false });
        return;
    }

    const firstObject = teamObjects.value?.[0];
    if (firstObject?.schema_definition_id !== props.schemaDefinition.id) {
        // Clear the current team objects if the type has changed
        dxTeamObject.pagedItems.value = null;
    }

    // Trigger loading the new team objects
    dxTeamObject.setOptions({ isListEnabled: true });
    dxTeamObject.setActiveFilter({
        schema_definition_id: props.schemaDefinition.id,
        type: teamObjectType.value
    });
}

function showMergeDialog(teamObject: TeamObject) {
    sourceTeamObject.value = teamObject;
    showMergeDialogRef.value = true;
}

async function performMerge(sourceObject: TeamObject, targetObject: TeamObject) {
    try {
        const response = await request.post(apiUrls.teams.mergeObjects({ sourceId: sourceObject.id, targetId: targetObject.id }));

        if (response) {
            showMergeDialogRef.value = false;
            sourceTeamObject.value = null;
            await loadTeamObjects();
        }
    } catch (error) {
        console.error("Merge error:", error);
    }
}
</script>
