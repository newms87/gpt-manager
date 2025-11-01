<template>
    <div class="flex items-center gap-3">
        <!-- Search Input -->
        <SearchBox
            v-model="keyword"
            placeholder="Search logs..."
            class="w-96"
        />

        <!-- Log Level Filter -->
        <SelectField
            :model-value="selectedLevels"
            :options="levelOptions"
            placeholder="All levels"
            select-class="dx-select-field-dense"
            multiple
            class="w-48"
            @update:model-value="emit('update:selectedLevels', $event)"
        />

        <!-- Clear All Filters -->
        <ActionButton
            v-if="hasActiveFilters"
            type="cancel"
            label="Clear"
            size="xs"
            color="slate"
            tooltip="Clear all filters"
            @click="clearFilters"
        />
    </div>
</template>

<script setup lang="ts">
import { SearchBox } from "@/components/Shared";
import { ActionButton, SelectField } from "quasar-ui-danx";
import { computed } from "vue";
import type { LogLevel } from "./logHelpers";

const props = defineProps<{
    selectedLevels: LogLevel[];
    availableLevels: LogLevel[];
}>();

const emit = defineEmits<{
    "update:selectedLevels": [levels: LogLevel[]];
}>();

const keyword = defineModel<string>("keyword");

const levelOptions = computed(() => {
    return props.availableLevels.map(level => ({
        label: level,
        value: level
    }));
});

const hasActiveFilters = computed((): boolean => {
    return keyword.value !== "" || props.selectedLevels.length > 0;
});

const clearFilters = (): void => {
    keyword.value = "";
    emit("update:selectedLevels", []);
};
</script>
