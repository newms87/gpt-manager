<template>
    <button
        type="button"
        :disabled="loading"
        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-slate-300 hover:border-slate-400"
        :class="{'text-green-600': !!cost, 'text-slate-400': !cost}"
        @click="$emit('click')"
    >
        <FaSolidDollarSign class="w-3 h-3 mr-1.5" />

        <!-- Loading state -->
        <template v-if="loading">
            <div class="animate-spin w-3 h-3 mr-1.5">
                <FaSolidSpinner class="w-3 h-3" />
            </div>
            <span>Loading...</span>
        </template>

        <!-- Cost display -->
        <template v-else>
            <span>{{ displayText }}</span>
        </template>
    </button>
</template>

<script setup lang="ts">
import { FaSolidDollarSign, FaSolidSpinner } from "danx-icon";
import { fCurrency } from "quasar-ui-danx";
import { computed } from "vue";

const props = withDefaults(defineProps<{
    cost: number | null | undefined;
    loading?: boolean;
}>(), {
    loading: false
});

defineEmits<{
    click: [];
}>();

const displayText = computed(() => {
    if (props.cost === null || props.cost === undefined) {
        return "No usage";
    }

    return fCurrency(props.cost);
});
</script>
