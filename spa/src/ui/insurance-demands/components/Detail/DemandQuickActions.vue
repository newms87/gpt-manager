<template>
    <UiCard>
        <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
                Quick Actions
            </h3>
        </template>

        <div class="flex flex-col space-y-2">
            <ActionButton
                type="edit"
                label="Edit Details"
                @click="$emit('edit')"
            />

            <DemandActionButtons
                v-if="demand"
                :demand="demand"
                size="md"
                :loading-states="loadingStates"
                class="w-full flex flex-col space-y-2"
                @extract-data="$emit('extract-data')"
                @write-demand="$emit('write-demand')"
            />

            <!-- Complete Button -->
            <ActionButton
                v-if="demand && demand.status !== DEMAND_STATUS.COMPLETED"
                type="check"
                color="green-invert"
                label="Mark Complete"
                :loading="loadingStates.complete"
                @click="$emit('complete')"
            />

            <!-- Set As Draft Button -->
            <ActionButton
                v-if="demand && demand.status === DEMAND_STATUS.COMPLETED"
                type="clock"
                color="slate"
                label="Set As Draft"
                :loading="loadingStates.setAsDraft"
                @click="$emit('set-as-draft')"
            />

            <ActionButton
                type="trash"
                color="red"
                label="Delete Demand"
                @click="$emit('delete')"
            />
        </div>
    </UiCard>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import { watchEffect } from "vue";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { DEMAND_STATUS } from "../../config";
import DemandActionButtons from "../DemandActionButtons.vue";

interface LoadingStates {
    extractData: boolean;
    writeDemand: boolean;
    complete?: boolean;
    setAsDraft?: boolean;
}

const props = defineProps<{
    demand: UiDemand | null;
    loadingStates: LoadingStates;
}>();

defineEmits<{
    "edit": [];
    "extract-data": [];
    "write-demand": [];
    "duplicate": [];
    "complete": [];
    "set-as-draft": [];
    "delete": [];
}>();

</script>
