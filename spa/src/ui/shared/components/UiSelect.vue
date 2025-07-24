<template>
  <div class="ui-select-wrapper">
    <label v-if="label" class="ui-select-label">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-1">*</span>
    </label>
    
    <div class="relative">
      <select
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        class="ui-select"
        :class="{
          'border-red-300 focus:border-red-500 focus:ring-red-500/20': error,
        }"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
      >
        <option v-if="placeholder" value="" disabled>
          {{ placeholder }}
        </option>
        
        <option
          v-for="option in options"
          :key="option.value"
          :value="option.value"
          :disabled="option.disabled"
        >
          {{ option.label }}
        </option>
      </select>
      
      <FaSolidChevronDown class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
    </div>
    
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
    
    <p v-else-if="hint" class="mt-1 text-sm text-slate-500">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { FaSolidChevronDown } from 'danx-icon';

export interface SelectOption {
  value: string | number;
  label: string;
  disabled?: boolean;
}

const props = withDefaults(defineProps<{
  modelValue?: string | number;
  label?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  error?: string;
  hint?: string;
  options: SelectOption[];
}>(), {
  disabled: false,
  required: false,
});

defineEmits<{
  'update:modelValue': [value: string];
  blur: [event: FocusEvent];
  focus: [event: FocusEvent];
}>();
</script>

<style scoped lang="scss">
.ui-select-wrapper {
  @apply w-full;
}

.ui-select-label {
  @apply block text-sm font-medium text-slate-700 mb-2;
}

.ui-select {
  @apply w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg;
  @apply focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500;
  @apply transition-colors duration-200;
  @apply disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed;
  @apply appearance-none bg-white;
}
</style>