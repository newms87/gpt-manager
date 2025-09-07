<template>
    <FullScreenDialog
        :model-value="true"
        closeable
        content-class="bg-slate-900 p-0 flex flex-col h-full"
        @close="$emit('close')"
    >
        <!-- Header -->
        <div class="bg-slate-800 p-4 border-b border-slate-700 flex-shrink-0">
            <h2 class="text-xxl font-semibold text-slate-200 py-2">
                Extracted Data
            </h2>
        </div>

        <!-- Content Area -->
        <div class="flex-grow min-h-0 flex gap-6 p-6">
            <!-- Loading State -->
            <div v-if="!teamObject" class="flex items-center justify-center h-full w-full">
                <div class="text-center">
                    <QSpinnerGears class="text-sky-500 w-12 h-12 mb-3" />
                    <p class="text-slate-400">No extracted data available</p>
                </div>
            </div>

            <!-- Main Content -->
            <template v-else>
                <!-- Left Sidebar: Tree View -->
                <div class="min-w-[25rem]">
                    <TeamObjectTreeView
                        :objects="[teamObject]"
                        :selected-object="selectedObject"
                        @select-object="onSelectObject"
                    />
                </div>

                <!-- Right Content: Detail View -->
                <div class="flex-1 min-w-0">
                    <TeamObjectDetailView
                        :object="selectedObject || teamObject"
                        :parent-object="parentObject"
                        @select-object="onSelectObject"
                    />
                </div>
            </template>
        </div>
    </FullScreenDialog>
</template>

<script setup lang="ts">
import { useTeamObjectUpdates } from "@/components/Modules/TeamObjects/composables/useTeamObjectUpdates";
import type { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectDetailView from "@/components/Modules/TeamObjects/TeamObjectDetailView.vue";
import TeamObjectTreeView from "@/components/Modules/TeamObjects/TeamObjectTreeView.vue";
import { FullScreenDialog } from "quasar-ui-danx";
import { computed, onUnmounted, ref, watch } from "vue";

const emit = defineEmits<{
    close: [];
}>();

const props = defineProps<{
    teamObject: TeamObject | null;
}>();

const selectedObject = ref<TeamObject | null>(null);
const parentObject = computed(() => {
    // If selectedObject is null, we are at the root level
    if (!selectedObject.value) return null;

    // Find parent by checking relations of the selected object
    const findParent = (obj: TeamObject, targetId: string): TeamObject | null => {
        for (const relatedList of Object.values(obj.relations || {})) {
            for (const related of relatedList) {
                if (related.id === targetId) {
                    return obj;
                }
                const parent = findParent(related, targetId);
                if (parent) return parent;
            }
        }
        return null;
    };

    return props.teamObject ? findParent(props.teamObject, selectedObject.value.id) : null;
});

// Initialize the TeamObject updates composable
const { subscribeToTeamObjectUpdates, unsubscribeFromAllUpdates } = useTeamObjectUpdates();

const onSelectObject = (object: TeamObject) => {
    selectedObject.value = object;
};

// Initialize when team object changes - don't set selectedObject, let it default to props.teamObject
watch(() => props.teamObject, (newTeamObject) => {
    if (newTeamObject) {
        // Only reset navigation state, don't copy the object reference
        selectedObject.value = null; // This will make template use teamObject

        // Subscribe to real-time updates for the root team object
        subscribeToTeamObjectUpdates(newTeamObject);
    }
}, { immediate: true });

// Clean up subscriptions when the dialog is closed
onUnmounted(() => {
    unsubscribeFromAllUpdates();
});
</script>
