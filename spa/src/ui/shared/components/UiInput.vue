<template>
  <div class="ui-input-wrapper">
    <label v-if="label" class="ui-input-label">
      {{ label }}
      <span v-if="required" class="text-red-500 ml-1">*</span>
    </label>
    
    <div class="relative">
      <component 
        v-if="prefixIcon" 
        :is="prefixIcon" 
        class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400"
      />
      
      <input
        :value="modelValue"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        class="ui-input"
        :class="{
          'pl-10': prefixIcon,
          'pr-10': suffixIcon,
          'border-red-300 focus:border-red-500 focus:ring-red-500/20': error,
        }"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
      />
      
      <component 
        v-if="suffixIcon" 
        :is="suffixIcon" 
        class="absolute right-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-slate-400"
      />
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
const props = withDefaults(defineProps<{
  modelValue?: string | number;
  type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url';
  label?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  error?: string;
  hint?: string;
  prefixIcon?: any;
  suffixIcon?: any;
}>(), {
  type: 'text',
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
.ui-input-wrapper {
  @apply w-full;
}

.ui-input-label {
  @apply block text-sm font-medium text-slate-700 mb-2;
}

.ui-input {
  @apply w-full px-3 py-2 border border-slate-300 rounded-lg;
  @apply focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500;
  @apply transition-colors duration-200;
  @apply disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed;
  @apply placeholder:text-slate-400;
}
</style>