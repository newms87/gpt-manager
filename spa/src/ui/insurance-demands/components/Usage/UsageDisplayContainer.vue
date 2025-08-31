<template>
    <div class="usage-display-container">
        <!-- Button State (Collapsed) -->
        <UsageDisplayButton
            v-if="displayState === 'button'"
            :cost="demand.usage_summary?.total_cost"
            @click="expandToSummary"
        />

        <!-- Summary State (Expanded) -->
        <UsageDisplaySummary
            v-else-if="displayState === 'summary'"
            :summary="demand.usage_summary"
            :allow-collapse="allowCollapse"
            @view-details="expandToTable"
            @collapse="collapseToButton"
        />

        <!-- Table State (Fully Expanded) -->
        <UsageDisplayTable
            v-else-if="displayState === 'table'"
            :demand="demand"
            :summary="demand.usage_summary"
            :allow-collapse="allowCollapse"
            @collapse-to-summary="collapseToSummary"
            @collapse-to-button="collapseToButton"
        />
    </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import type { UiDemand } from "../../shared/types";
import UsageDisplayButton from "./UsageDisplayButton.vue";
import UsageDisplaySummary from "./UsageDisplaySummary.vue";
import UsageDisplayTable from "./UsageDisplayTable.vue";

const props = withDefaults(defineProps<{
    demand: UiDemand;
    defaultExpanded?: boolean;
    allowCollapse?: boolean;
}>(), {
    defaultExpanded: false,
    allowCollapse: true
});

// Simple display state management (no API calls needed - data comes with demand)
type DisplayState = "button" | "summary" | "table";
const displayState = ref<DisplayState>(props.defaultExpanded ? "summary" : "button");

const expandToSummary = () => {
    displayState.value = "summary";
};

const expandToTable = () => {
    displayState.value = "table";
};

const collapseToButton = () => {
    displayState.value = "button";
};

const collapseToSummary = () => {
    displayState.value = "summary";
};
</script>
