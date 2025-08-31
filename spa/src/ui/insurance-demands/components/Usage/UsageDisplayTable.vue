<template>
    <div class="usage-display-table space-y-4">
        <!-- Summary Card (smaller version) -->
        <div class="transform scale-95 origin-top">
            <UsageDisplaySummary
                :summary="summary"
                title="Usage Summary"
                @view-details="$emit('collapse-to-summary')"
            />
        </div>

        <!-- Events Details Card -->
        <UiCard>
            <template #header>
                <h3 class="text-lg font-semibold text-slate-800">
                    Usage Details
                </h3>
            </template>

            <!-- Loading State for Events -->
            <div v-if="loadingEvents" class="p-6 flex items-center justify-center">
                <div class="animate-spin w-4 h-4 mr-3 border-2 border-blue-500 border-t-transparent rounded-full"></div>
                <span class="text-slate-600">Loading usage events...</span>
            </div>

            <!-- Events Table Content -->
            <div v-else>
                <UsageEventsTableModern :filter="usageEventsFilter" />
            </div>
        </UiCard>

        <!-- Collapse Controls -->
        <div v-if="allowCollapse" class="flex justify-center space-x-4">
            <ActionButton
                type="cancel"
                size="sm"
                label="Hide Details"
                @click="$emit('collapse-to-summary')"
            />
            <ActionButton
                type="cancel"
                size="sm"
                label="Close All"
                @click="$emit('collapse-to-button')"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ActionButton } from "quasar-ui-danx";
import { computed } from "vue";
import { UiCard } from "../../../shared/components";
import type { UiDemand, UsageSummary } from "../../shared/types";
import UsageDisplaySummary from "./UsageDisplaySummary.vue";
import UsageEventsTableModern from "./UsageEventsTableModern.vue";

const props = defineProps<{
    demand: UiDemand;
    summary: UsageSummary | null;
    loadingEvents?: boolean;
    allowCollapse?: boolean;
}>();

const usageEventsFilter = computed(() => ({
    "usageEventSubscribers.subscriber_type": "App\\Models\\UiDemand",
    "usageEventSubscribers.subscriber_id": props.demand.id
}));

defineEmits<{
    "collapse-to-summary": [];
    "collapse-to-button": [];
}>();
</script>

<style scoped lang="scss">
.transform.scale-95 {
    transform: scale(0.95);
}
</style>
