<template>
  <div class="p-6 h-full overflow-auto flex flex-col">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-200">Component Playground</h1>

      <QTabs
        :model-value="activeTab"
        class="text-slate-300"
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
        <QTab
          name="playground.audit-cards"
          label="Audit Cards"
          @click="router.push({ name: 'playground.audit-cards' })"
        />
        <QTab
          name="playground.schema-definition"
          label="Schema Definition"
          @click="router.push({ name: 'playground.schema-definition' })"
        />
      </QTabs>
    </div>

    <RouterView class="flex-1 min-h-0" />
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
