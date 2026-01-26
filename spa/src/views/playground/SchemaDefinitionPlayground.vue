<template>
  <div class="flex flex-col h-full">
    <!-- Controls Bar -->
    <div class="flex items-center gap-6 mb-4 p-4 bg-slate-800 rounded-lg border border-slate-600 flex-shrink-0">
      <!-- Mode Toggles -->
      <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="selectionEnabled"
            type="checkbox"
            class="w-4 h-4 rounded border-slate-500 bg-slate-700 text-sky-600 focus:ring-sky-500 focus:ring-offset-0"
          />
          <span class="text-sm text-slate-400">Enable Selection</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="editEnabled"
            type="checkbox"
            class="w-4 h-4 rounded border-slate-500 bg-slate-700 text-sky-600 focus:ring-sky-500 focus:ring-offset-0"
          />
          <span class="text-sm text-slate-400">Enable Edit</span>
        </label>
      </div>

      <div class="w-px h-6 bg-slate-600" />

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

    <!-- Diagram Canvas -->
    <div class="flex-1 min-h-0 bg-slate-800 rounded-lg border border-slate-600 overflow-hidden">
      <FragmentSelectorCanvas
        :key="`${selectionMode}-${recursive}`"
        :schema="schema"
        v-model="selection"
        :selection-enabled="selectionEnabled"
        :edit-enabled="editEnabled"
        :selection-mode="selectionMode"
        :recursive="recursive"
        :type-filter="typeFilter"
        @update:schema="schema = $event"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import FragmentSelectorCanvas from "@/components/Modules/SchemaEditor/FragmentSelector/FragmentSelectorCanvas.vue";
import { medicalRecordSchema } from "@/constants/demoSchemas";
import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { ref } from "vue";

// Selection state for the FragmentSelectorCanvas
const selection = ref<FragmentSelector | null>(null);

// Schema state (reactive copy for editing)
const schema = ref<JsonSchema>(JSON.parse(JSON.stringify(medicalRecordSchema)));

// Mode toggles
const selectionEnabled = ref(true);
const editEnabled = ref(false);

// Control options
const selectionMode = ref<"by-model" | "by-property">("by-property");
const recursive = ref<boolean>(true);
const typeFilter = ref<JsonSchemaType | null>(null);

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
</script>
