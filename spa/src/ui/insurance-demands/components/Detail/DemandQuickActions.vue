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
                :demand="demand"
                size="md"
                :loading-states="loadingStates"
                class="w-full flex flex-col space-y-2"
                @extract-data="handleExtractData"
                @write-demand="showTemplateSelector = true"
            />

            <!-- Complete Button -->
            <ActionButton
                v-if="demand.status !== DEMAND_STATUS.COMPLETED"
                type="check"
                color="green-invert"
                label="Mark Complete"
                :loading="loadingStates.complete || isCompleting"
                @click="handleComplete"
            />

            <!-- Set As Draft Button -->
            <ActionButton
                v-if="demand.status === DEMAND_STATUS.COMPLETED"
                type="clock"
                color="slate"
                label="Set As Draft"
                :loading="loadingStates.setAsDraft || isSettingAsDraft"
                @click="handleSetAsDraft"
            />

            <ActionButton
                type="trash"
                color="red"
                label="Delete Demand"
                :loading="isDeleting"
                @click="handleDelete"
            />
        </div>

        <!-- Template Selector Dialog -->
        <DemandTemplateSelector
            v-if="showTemplateSelector"
            @confirm="handleWriteDemandWithTemplate"
            @close="showTemplateSelector = false"
        />
    </UiCard>
</template>

<script setup lang="ts">
import { DemandTemplateSelector } from "@/ui/demand-templates/components";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { UiCard } from "../../../shared";
import type { UiDemand } from "../../../shared/types";
import { useDemands } from "../../composables";
import { DEMAND_STATUS } from "../../config";
import DemandActionButtons from "../DemandActionButtons.vue";

const emit = defineEmits<{
    "edit": [];
    "extract-data": [];
    "write-demand": [];
}>();

const props = defineProps<{
    demand: UiDemand;
}>();

const router = useRouter();
const { updateDemand, extractData, writeDemand, deleteDemand: deleteDemandAction } = useDemands();

// Local loading states
const isCompleting = ref(false);
const isSettingAsDraft = ref(false);
const isDeleting = ref(false);

// Computed properties for loading states based on workflow status
const extractingData = computed(() => props.demand?.is_extract_data_running || false);
const writingDemand = computed(() => props.demand?.is_write_demand_running || false);

const loadingStates = computed(() => ({
    extractData: extractingData.value,
    writeDemand: writingDemand.value,
    complete: isCompleting.value,
    setAsDraft: isSettingAsDraft.value
}));

// Action handlers
const handleExtractData = async () => {
    await extractData(props.demand);
};

const showTemplateSelector = ref(false);
const handleWriteDemandWithTemplate = async (template: any, instructions: string) => {
    showTemplateSelector.value = false; // Close the modal
    await writeDemand(props.demand, template.id, instructions);
};

const handleComplete = async () => {
    try {
        isCompleting.value = true;

        await updateDemand(props.demand.id, {
            status: DEMAND_STATUS.COMPLETED,
            completed_at: new Date().toISOString()
        });
    } catch (err: any) {
        console.error("❌ Failed to complete demand:", err);
    } finally {
        isCompleting.value = false;
    }
};

const handleSetAsDraft = async () => {
    try {
        isSettingAsDraft.value = true;

        await updateDemand(props.demand.id, {
            status: DEMAND_STATUS.DRAFT,
            completed_at: null
        });
    } catch (err: any) {
        console.error("❌ Failed to set demand as draft:", err);
    } finally {
        isSettingAsDraft.value = false;
    }
};

const handleDelete = async () => {
    if (confirm("Are you sure you want to delete this demand? This action cannot be undone.")) {
        try {
            isDeleting.value = true;
            await deleteDemandAction(props.demand.id);
            router.push("/ui/demands");
        } catch (err: any) {
            console.error("❌ Failed to delete demand:", err);
        } finally {
            isDeleting.value = false;
        }
    }
};

</script>
