<template>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div
            v-for="stat in statsCards"
            :key="stat.key"
            class="bg-white rounded-xl border transition-all duration-200 p-4 cursor-pointer relative"
            :class="[
        selectedStatus === stat.key
          ? 'border-blue-500 shadow-lg ring-2 ring-blue-100 transform scale-105'
          : 'border-slate-200/60 hover:shadow-md hover:border-slate-300',
        stat.key === 'total' && 'cursor-default'
      ]"
            @click="handleCardClick(stat.key)"
        >
            <div class="flex items-center">
                <div
                    class="w-12 h-12 rounded-lg flex items-center justify-center mr-3 transition-all duration-200"
                    :class="[
            stat.bgColor,
            selectedStatus === stat.key ? 'transform scale-110' : ''
          ]"
                >
                    <component :is="stat.icon" class="w-6 h-6 text-white" />
                </div>

                <div>
                    <p class="text-2xl font-bold text-slate-800">
                        {{ stat.value }}
                    </p>
                    <p class="text-sm text-slate-600">
                        {{ stat.label }}
                    </p>
                </div>
            </div>

            <!-- Active indicator -->
            <div
                v-if="selectedStatus === stat.key"
                class="absolute top-2 right-2 w-3 h-3 bg-blue-500 rounded-full"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidClock, FaSolidFile, FaSolidXmark } from "danx-icon";
import { computed } from "vue";
import { useDemands } from "../composables";

const props = defineProps<{
    selectedStatus?: string;
}>();

const emit = defineEmits<{
    "filter-change": [status: string | undefined];
}>();

const { stats } = useDemands();

const handleCardClick = (key: string) => {
    // Don't allow filtering by "total" - it's just a summary
    if (key === "total") return;

    // If the same status is clicked, clear the filter
    if (props.selectedStatus === key) {
        emit("filter-change", undefined);
    } else {
        emit("filter-change", key);
    }
};

const statsCards = computed(() => [
    {
        key: "total",
        label: "Total",
        value: stats.value.total,
        icon: FaSolidFile,
        bgColor: "bg-gradient-to-r from-slate-500 to-slate-600"
    },
    {
        key: "Draft",
        label: "Draft",
        value: stats.value.draft,
        icon: FaSolidClock,
        bgColor: "bg-gradient-to-r from-slate-400 to-slate-500"
    },
    {
        key: "Completed",
        label: "Completed",
        value: stats.value.completed,
        icon: FaSolidCheck,
        bgColor: "bg-gradient-to-r from-green-500 to-green-600"
    },
    {
        key: "Failed",
        label: "Failed",
        value: stats.value.failed,
        icon: FaSolidXmark,
        bgColor: "bg-gradient-to-r from-red-500 to-red-600"
    }
]);
</script>
