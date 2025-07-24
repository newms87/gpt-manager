<template>
  <div class="ui-textarea-wrapper">
    <label v-if="label" class="ui-textarea-label">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-1">*</span>
    </label>
    
    <textarea
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :rows="rows"
      class="ui-textarea"
      :class="{
        'border-red-300 focus:border-red-500 focus:ring-red-500/20': error,
      }"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      @blur="$emit('blur', $event)"
      @focus="$emit('focus', $event)"
    />
    
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
    
    <p v-else-if="hint" class="mt-1 text-sm text-slate-500">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue?: string;
  label?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  error?: string;
  hint?: string;
  rows?: number;
}>(), {
  disabled: false,
  required: false,
  rows: 3,
});

defineEmits<{
  'update:modelValue': [value: string];
  blur: [event: FocusEvent];
  focus: [event: FocusEvent];
}>();
</script>

<style scoped lang="scss">
.ui-textarea-wrapper {
  @apply w-full;
}

.ui-textarea-label {
  @apply block text-sm font-medium text-slate-700 mb-2;
}

.ui-textarea {
  @apply w-full px-3 py-2 border border-slate-300 rounded-lg;
  @apply focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500;
  @apply transition-colors duration-200;
  @apply disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed;
  @apply placeholder:text-slate-400;
  @apply resize-y min-h-[80px];
}
</style>