<template>
  <div class="ui-main-layout">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 overflow-hidden">
      <header v-if="$slots.header" class="border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-blue-50/50">
        <slot name="header" />
      </header>
      
      <main class="p-6">
        <slot />
      </main>
      
      <footer v-if="$slots.footer" class="border-t border-slate-200/60 bg-slate-50/50">
        <slot name="footer" />
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
// No props needed - this is a layout wrapper
</script>

<style scoped lang="scss">
.ui-main-layout {
  @apply min-h-full;
}
</style>