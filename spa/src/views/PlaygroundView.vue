<template>
  <div class="p-6 h-full overflow-auto">
    <h1 class="text-2xl font-bold mb-6 text-slate-200">Component Playground</h1>

    <QTabs
      :model-value="activeTab"
      class="mb-6 text-slate-300"
      active-color="primary"
      indicator-color="primary"
      narrow-indicator
    >
      <QTab
        name="playground.code-viewer"
        label="Code Viewer"
        @click="router.push({ name: 'playground.code-viewer' })"
      />
      <QTab
        name="playground.markdown-editor"
        label="Markdown Editor"
        @click="router.push({ name: 'playground.markdown-editor' })"
      />
    </QTabs>

    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { QTab, QTabs } from "quasar";

const route = useRoute();
const router = useRouter();

const activeTab = computed(() => route.name as string);
</script>
