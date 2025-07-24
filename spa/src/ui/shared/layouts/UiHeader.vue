<template>
  <header class="bg-white/80 backdrop-blur-lg border-b border-slate-200/60 flex-shrink-0">
    <div class="flex items-center justify-between px-6 py-4">
      <div class="flex items-center space-x-4">
        <button 
          v-if="config.showSidebar"
          @click="toggleSidebar"
          class="p-2 rounded-lg hover:bg-slate-100 transition-colors"
        >
          <FaSolidBars class="w-5 h-5 text-slate-600" />
        </button>
        
        <div class="flex items-center space-x-3">
          <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
            <FaSolidFile class="w-4 h-4 text-white" />
          </div>
          <h1 class="text-xl font-bold bg-gradient-to-r from-slate-800 to-slate-600 bg-clip-text text-transparent">
            Insurance Demands
          </h1>
        </div>
      </div>
      
      <div class="flex items-center space-x-4">
        <div class="hidden md:flex items-center space-x-3 text-sm text-slate-600">
          <FaSolidUser class="w-4 h-4" />
          <span>Welcome back</span>
        </div>
        
        <UiUserMenu />
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { FaSolidBars, FaSolidFile, FaSolidUser } from 'danx-icon';
import { useUiLayout } from '../composables';
import UiUserMenu from '../components/UiUserMenu.vue';

const { config, toggleSidebar } = useUiLayout();
</script>