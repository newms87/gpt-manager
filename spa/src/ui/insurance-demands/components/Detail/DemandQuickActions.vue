<template>
    <UiCard>
        <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
                Quick Actions
            </h3>
        </template>

        <div class="flex flex-col space-y-2">
            <DemandActionButtons
                :demand="demand"
                size="md"
                class="w-full flex flex-col space-y-2"
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
    </UiCard>
</template>

<script setup lang="ts">
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
}>();

const props = defineProps<{
    demand: UiDemand;
}>();

const router = useRouter();
const { updateDemand, deleteDemand: deleteDemandAction } = useDemands();

// Local loading states
const isCompleting = ref(false);
const isSettingAsDraft = ref(false);
const isDeleting = ref(false);

// Computed loading states for remaining buttons
const loadingStates = computed(() => ({
    complete: isCompleting.value,
    setAsDraft: isSettingAsDraft.value
}));

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
