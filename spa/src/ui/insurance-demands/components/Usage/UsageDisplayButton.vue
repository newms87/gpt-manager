<template>
  <ActionButton
    type="view"
    :label="displayText"
    :saving="loading"
    @click="$emit('click')"
  />
</template>

<script setup lang="ts">
import { computed } from "vue";
import { ActionButton, fCurrency } from "quasar-ui-danx";

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
  if (props.loading) {
    return "Loading usage...";
  }
  
  if (props.cost === null || props.cost === undefined || props.cost === 0) {
    return "No usage";
  }
  
  return fCurrency(props.cost);
});
</script>