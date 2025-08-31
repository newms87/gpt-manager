<template>
    <UiCard>
        <template #header>
            <h3 class="text-lg font-semibold text-slate-800">
                {{ title }}
            </h3>
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="p-6 flex items-center justify-center">
            <div class="animate-spin w-4 h-4 mr-3 border-2 border-blue-500 border-t-transparent rounded-full"></div>
            <span class="text-slate-600">Loading usage data...</span>
        </div>

        <!-- Summary Content -->
        <div v-else class="space-y-4">
            <!-- Empty state -->
            <div v-if="!summary || !hasUsageData" class="text-center py-8">
                <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <FaSolidDollarSign class="w-6 h-6 text-slate-400" />
                </div>
                <p class="text-slate-500 text-sm">No usage data available</p>
            </div>

            <!-- Summary content -->
            <div v-else class="space-y-4">
                <!-- Total Cost -->
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                    <div class="flex-x space-x-3 items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <FaSolidDollarSign class="w-4 h-4 text-green-600" />
                        </div>
                        <div class="text-sm font-medium text-slate-700">
                            Total Cost
                        </div>
                        <a v-if="showViewDetails" class="text-sm" @click="$emit('view-details')">
                            {{ summary.count || "no" }} events
                        </a>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold" :class="costColorClass">
                            {{ fCurrency(summary.total_cost) }}
                        </p>
                    </div>
                </div>

                <!-- Token Usage -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Input Tokens -->
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center mb-2">
                            <FaSolidArrowRight class="w-4 h-4 text-blue-600 mr-2" />
                            <span class="text-sm font-medium text-slate-700">Input</span>
                        </div>
                        <div class="flex-x space-x-2">
                            <div class="text-lg font-semibold text-blue-700 mb-1">
                                {{ fNumber(summary.input_tokens) }}
                            </div>
                            <div class="text-sm text-slate-500">tokens</div>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ fCurrency(summary.input_cost) }}
                        </p>
                    </div>

                    <!-- Output Tokens -->
                    <div class="p-3 bg-purple-50 rounded-lg">
                        <div class="flex items-center mb-2">
                            <FaSolidArrowLeft class="w-4 h-4 text-purple-600 mr-2" />
                            <span class="text-sm font-medium text-slate-700">Output</span>
                        </div>
                        <div class="flex-x space-x-2">
                            <div class="text-lg font-semibold text-purple-700 mb-1">
                                {{ fNumber(summary.output_tokens) }}
                            </div>
                            <div class="text-sm text-slate-500">tokens</div>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ fCurrency(summary.output_cost) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Collapse Button -->
            <div v-if="allowCollapse" class="flex justify-center pt-4">
                <ActionButton
                    type="cancel"
                    size="sm"
                    label="Collapse"
                    @click="$emit('collapse')"
                />
            </div>
        </div>
    </UiCard>
</template>

<script setup lang="ts">
import { FaSolidArrowLeft, FaSolidArrowRight, FaSolidDollarSign } from "danx-icon";
import { ActionButton, fCurrency, fNumber } from "quasar-ui-danx";
import { computed } from "vue";
import { UiCard } from "../../../shared/components";
import type { UsageSummary } from "../../shared/types";

const props = withDefaults(defineProps<{
    summary: UsageSummary | null;
    loading?: boolean;
    allowCollapse?: boolean;
    title?: string;
    showViewDetails?: boolean;
}>(), {
    title: "Usage Summary",
    showViewDetails: true
});

defineEmits<{
    "view-details": [];
    "collapse": [];
}>();

const costColorClass = computed(() => {
    const cost = props.summary?.total_cost || 0;
    if (cost === 0) return "text-slate-500";
    if (cost < 0.01) return "text-green-600";
    if (cost < 0.10) return "text-yellow-600";
    if (cost < 1.00) return "text-orange-600";
    return "text-red-600";
});

const hasUsageData = computed(() => {
    if (!props.summary) return false;

    return (
        props.summary.total_cost !== null ||
        props.summary.total_input_tokens !== null ||
        props.summary.total_output_tokens !== null ||
        props.summary.event_count > 0
    );
});
</script>
