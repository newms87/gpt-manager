<template>
  <div class="flex flex-col items-center justify-center py-12 px-6 text-center">
    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
      <FaSolidChartBar class="w-8 h-8 text-slate-400" />
    </div>
    
    <h3 class="text-lg font-medium text-slate-900 mb-2">
      No usage data available
    </h3>
    
    <p class="text-slate-500 text-sm max-w-sm">
      Usage information will appear here once API calls are made for this demand.
    </p>
  </div>
</template>

<script setup lang="ts">
import { FaSolidChartBar } from "danx-icon";
</script>