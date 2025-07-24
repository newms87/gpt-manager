<template>
  <aside 
    class="bg-white/90 backdrop-blur-lg border-r border-slate-200/60 transition-all duration-300 flex-shrink-0"
    :class="{
      'w-16': config.sidebarCollapsed,
      'w-64': !config.sidebarCollapsed,
    }"
  >
    <nav class="p-4 space-y-2 flex-1 overflow-y-auto">
      <UiNavItem 
        v-for="item in navigation" 
        :key="item.route"
        :item="item"
        :collapsed="config.sidebarCollapsed"
      />
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { useUiLayout, useUiNavigation } from '../composables';
import UiNavItem from '../components/UiNavItem.vue';

const { config } = useUiLayout();
const { navigation } = useUiNavigation();
</script>