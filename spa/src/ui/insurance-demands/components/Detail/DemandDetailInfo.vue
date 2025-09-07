<template>
    <UiCard>
        <template #header>
            <div class="flex-x">
                <h3 class="text-lg font-semibold text-slate-800 flex-grow">
                    Demand Details
                </h3>
                <ActionButton type="edit" @click="editMode = true" v-if="demand && !editMode" />
            </div>
        </template>

        <div v-if="editMode" class="space-y-4">
            <DemandForm
                mode="edit"
                :initial-data="demand"
                @submit="$emit('update', $event)"
                @cancel="editMode = false"
            />
        </div>

        <div v-else-if="demand" class="space-y-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Title</label>
                <p class="mt-1 text-slate-800">{{ demand.title }}</p>
            </div>

            <div v-if="demand.description">
                <label class="text-sm font-medium text-slate-700">Description</label>
                <p class="mt-1 text-slate-800 whitespace-pre-wrap">{{ demand.description }}</p>
            </div>
        </div>
    </UiCard>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import { DemandForm } from "../";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";

const props = defineProps<{
    demand: UiDemand | null;
}>();

const editMode = defineModel<boolean>("editMode");
defineEmits<{
    "update": [data: { title: string; description: string; files?: any[] }];
    "cancel-edit": [];
}>();
</script>
