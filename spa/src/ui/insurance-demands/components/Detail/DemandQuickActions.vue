<template>
    <UiCard>
        <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
                Quick Actions
            </h3>
        </template>

        <div class="flex flex-col space-y-2">
            <!-- Google Docs Auth Warning -->
            <div v-if="!isAuthorized" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-start space-x-2">
                <FaSolidTriangleExclamation class="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-yellow-800 mb-2">
                        Connect Google Docs to enable Extract Data, Medical Summary, and Demand Letter actions
                    </p>
                    <GoogleDocsAuth :compact="true" />
                </div>
            </div>

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
                :saving="loadingStates.complete || isCompleting"
                @click="handleComplete"
            />

            <!-- Set As Draft Button -->
            <ActionButton
                v-if="demand.status === DEMAND_STATUS.COMPLETED"
                type="clock"
                color="slate"
                label="Set As Draft"
                :saving="loadingStates.setAsDraft || isSettingAsDraft"
                @click="handleSetAsDraft"
            />

            <ActionButton
                type="trash"
                color="red"
                label="Delete Demand"
                :saving="isDeleting"
                @click="handleDelete"
            />
        </div>

        <!-- Delete Confirmation Dialog -->
        <ConfirmDialog
            v-if="showDeleteConfirm"
            class="ui-mode"
            title="Delete Demand?"
            content="Are you sure you want to delete this demand? This action cannot be undone."
            color="negative"
            :is-saving="isDeleting"
            @confirm="confirmDelete"
            @close="showDeleteConfirm = false"
        />
    </UiCard>
</template>

<script setup lang="ts">
import { FaSolidTriangleExclamation } from "danx-icon";
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { GoogleDocsAuth, UiCard } from "../../../shared";
import { useGoogleDocsAuth } from "../../../shared/composables/useGoogleDocsAuth";
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
const { isAuthorized } = useGoogleDocsAuth();

// Local loading states
const isCompleting = ref(false);
const isSettingAsDraft = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);

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

const handleDelete = () => {
    showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
    try {
        isDeleting.value = true;
        await deleteDemandAction(props.demand.id);
        router.push("/ui/demands");
    } catch (err: any) {
        console.error("❌ Failed to delete demand:", err);
    } finally {
        isDeleting.value = false;
        showDeleteConfirm.value = false;
    }
};

</script>
