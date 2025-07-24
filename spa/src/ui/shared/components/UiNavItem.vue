<template>
  <div class="ui-nav-item">
    <router-link
      :to="item.route"
      class="nav-link"
      :class="{
        'nav-link-active': isActive,
        'nav-link-collapsed': collapsed,
      }"
    >
      <component 
        :is="item.icon" 
        class="nav-icon" 
        :class="collapsed ? 'w-6 h-6' : 'w-5 h-5'"
      />
      
      <span v-if="!collapsed" class="nav-text">
        {{ item.title }}
      </span>
    </router-link>
    
    <!-- Tooltip for collapsed state -->
    <div 
      v-if="collapsed && item.title"
      class="nav-tooltip"
    >
      {{ item.title }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import type { UiNavigation } from '../types';

const props = defineProps<{
  item: UiNavigation;
  collapsed: boolean;
}>();

const route = useRoute();

const isActive = computed(() => {
  return route.path.startsWith(props.item.route);
});
</script>

<style scoped lang="scss">
.ui-nav-item {
  @apply relative;
}

.nav-link {
  @apply flex items-center px-3 py-2 rounded-lg text-slate-700;
  @apply hover:bg-slate-100 hover:text-slate-900;
  @apply transition-all duration-200;
  @apply no-underline;
}

.nav-link-active {
  @apply bg-gradient-to-r from-blue-500 to-blue-600 text-white;
  @apply shadow-lg;
  
  &:hover {
    @apply from-blue-600 to-blue-700;
  }
}

.nav-link-collapsed {
  @apply justify-center px-2;
}

.nav-icon {
  @apply flex-shrink-0;
}

.nav-text {
  @apply ml-3 font-medium;
}

.nav-tooltip {
  @apply absolute left-16 top-2 z-50;
  @apply bg-slate-800 text-white text-sm px-2 py-1 rounded;
  @apply opacity-0 pointer-events-none;
  @apply transition-opacity duration-200;
  
  .ui-nav-item:hover & {
    @apply opacity-100;
  }
}
</style>