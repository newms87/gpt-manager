<template>
  <!-- Sidebar is overlay only - no inline thin version -->
  <div>
    <!-- Expanded sidebar overlay (fixed to viewport) -->
    <Transition name="slide">
      <aside
        v-if="!config.sidebarCollapsed"
        class="fixed left-0 top-0 w-64 h-screen bg-white/95 backdrop-blur-lg border-r border-slate-200/60 shadow-xl z-50 flex flex-col pt-16"
      >
        <nav class="p-4 space-y-2 flex-1 overflow-y-auto">
          <UiNavItem
            v-for="item in navigation"
            :key="item.route"
            :item="item"
            :collapsed="false"
          />
        </nav>

        <!-- Google Docs Auth Section -->
        <div class="border-t border-slate-200/60 px-4 py-4">
          <GoogleDocsAuth :collapsed="false" />
        </div>
      </aside>
    </Transition>

    <!-- Backdrop to close expanded sidebar -->
    <Transition name="fade">
      <div
        v-if="!config.sidebarCollapsed"
        class="fixed inset-0 bg-black/20 z-40"
        @click="setSidebarCollapsed(true)"
      />
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { useUiLayout, useUiNavigation } from '../composables';
import UiNavItem from '../components/UiNavItem.vue';
import GoogleDocsAuth from '../components/GoogleDocsAuth.vue';

const { config, setSidebarCollapsed } = useUiLayout();
const { navigation } = useUiNavigation();
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
