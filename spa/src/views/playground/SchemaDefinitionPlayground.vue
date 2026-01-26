<template>
  <div class="flex flex-col h-full">
    <!-- Controls Bar -->
    <div class="flex items-center gap-6 mb-4 p-4 bg-slate-800 rounded-lg border border-slate-600 flex-shrink-0">
      <!-- Selection Mode -->
      <div class="flex items-center gap-3">
        <span class="text-sm text-slate-400">Selection Mode:</span>
        <div class="flex gap-1">
          <button
            v-for="mode in selectionModes"
            :key="mode.value"
            class="px-3 py-1 text-xs rounded transition-colors"
            :class="selectionMode === mode.value
              ? 'bg-sky-600 text-white'
              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
            @click="selectionMode = mode.value"
          >
            {{ mode.label }}
          </button>
        </div>
      </div>

      <!-- Recursive Toggle -->
      <div class="flex items-center gap-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="recursive"
            type="checkbox"
            class="w-4 h-4 rounded border-slate-500 bg-slate-700 text-sky-600 focus:ring-sky-500 focus:ring-offset-0"
          />
          <span class="text-sm text-slate-400">Recursive</span>
        </label>
      </div>

      <!-- Type Filter -->
      <div class="flex items-center gap-3">
        <span class="text-sm text-slate-400">Type Filter:</span>
        <div class="flex gap-1">
          <button
            v-for="filter in typeFilters"
            :key="filter.value ?? 'all'"
            class="px-3 py-1 text-xs rounded transition-colors"
            :class="typeFilter === filter.value
              ? 'bg-sky-600 text-white'
              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
            @click="typeFilter = filter.value"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content: Diagram + Sidebar -->
    <div class="flex flex-1 min-h-0 gap-4">
      <!-- Diagram Canvas -->
      <div class="flex-1 min-w-0 bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
        <FragmentSelectorCanvas
          :key="`${selectionMode}-${recursive}`"
          :schema="medicalRecordSchema"
          v-model="selection"
          :selection-mode="selectionMode"
          :recursive="recursive"
          :type-filter="typeFilter"
        />
      </div>

      <!-- Selection Sidebar -->
      <div class="w-80 flex-shrink-0 flex flex-col bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
        <!-- Sidebar Header with counts -->
        <div class="flex items-center justify-between px-4 py-3 bg-slate-700 border-b border-slate-600">
          <span class="text-sm font-medium text-slate-200">Selection</span>
          <div class="flex items-center gap-3 text-xs text-slate-400">
            <span>Models: {{ modelCount }}</span>
            <span>Props: {{ propertyCount }}</span>
          </div>
        </div>

        <!-- Format Toggle -->
        <div class="flex items-center gap-2 px-4 py-2 border-b border-slate-600">
          <button
            v-for="fmt in outputFormats"
            :key="fmt"
            class="px-2 py-1 text-xs rounded transition-colors"
            :class="outputFormat === fmt
              ? 'bg-sky-600 text-white'
              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
            @click="setOutputFormat(fmt)"
          >
            {{ fmt.toUpperCase() }}
          </button>
        </div>

        <!-- Code Viewer -->
        <div class="flex-1 min-h-0 overflow-auto">
          <CodeViewer
            :model-value="selection"
            :format="outputFormat"
            editor-class="p-3"
            hide-footer
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import FragmentSelectorCanvas from "@/components/Modules/SchemaEditor/FragmentSelector/FragmentSelectorCanvas.vue";
import { medicalRecordSchema } from "@/constants/demoSchemas";
import { FragmentSelector, JsonSchemaType } from "@/types";
import { CodeViewer, getItem, setItem } from "quasar-ui-danx";
import { computed, ref } from "vue";

// Selection state for the FragmentSelectorCanvas
const selection = ref<FragmentSelector | null>(null);

// Control options
const selectionMode = ref<"by-model" | "by-property">("by-property");
const recursive = ref<boolean>(true);
const typeFilter = ref<JsonSchemaType | null>(null);

// Output format for sidebar (JSON or YAML)
const outputFormat = ref<"json" | "yaml">((getItem("fragmentSelector.outputFormat") as "json" | "yaml") ?? "json");
const outputFormats: ("json" | "yaml")[] = ["json", "yaml"];

function setOutputFormat(format: "json" | "yaml") {
  outputFormat.value = format;
  setItem("fragmentSelector.outputFormat", format);
}

const selectionModes = [
  { value: "by-property" as const, label: "By Property" },
  { value: "by-model" as const, label: "By Model" }
];

const typeFilters = [
  { value: null, label: "All" },
  { value: "string" as JsonSchemaType, label: "String" },
  { value: "number" as JsonSchemaType, label: "Number" },
  { value: "boolean" as JsonSchemaType, label: "Boolean" }
];

// Helper function to count models and properties in a FragmentSelector tree
function countSelectionItems(selector: FragmentSelector | null): { models: number; properties: number } {
  if (!selector) return { models: 0, properties: 0 };

  let models = 0;
  let properties = 0;

  // Count self if it's a model type
  if (selector.type === "object" || selector.type === "array") {
    models++;
  } else {
    properties++;
  }

  // Recursively count children
  if (selector.children) {
    for (const child of Object.values(selector.children)) {
      const childCounts = countSelectionItems(child);
      models += childCounts.models;
      properties += childCounts.properties;
    }
  }

  return { models, properties };
}

// Simple computed properties for counts
const modelCount = computed(() => countSelectionItems(selection.value).models);
const propertyCount = computed(() => countSelectionItems(selection.value).properties);
</script>
