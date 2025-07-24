<template>
  <div class="relative">
    <button
      @click="showMenu = !showMenu"
      class="flex items-center space-x-2 p-2 rounded-lg hover:bg-slate-100 transition-colors"
    >
      <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
        <FaSolidUser class="w-4 h-4 text-white" />
      </div>
      <FaSolidChevronDown class="w-3 h-3 text-slate-500" />
    </button>
    
    <div
      v-if="showMenu"
      class="absolute right-0 top-12 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-50"
    >
      <router-link
        to="/ui/account"
        class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
        @click="showMenu = false"
      >
        <FaSolidGear class="w-4 h-4 mr-3" />
        Account Settings
      </router-link>
      
      <router-link
        to="/ui/subscription"
        class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
        @click="showMenu = false"
      >
        <FaSolidCreditCard class="w-4 h-4 mr-3" />
        Subscription
      </router-link>
      
      <hr class="my-1 border-slate-200" />
      
      <button
        @click="logout"
        class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"
      >
        <FaSolidRightFromBracket class="w-4 h-4 mr-3" />
        Sign Out
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { 
  FaSolidUser, 
  FaSolidChevronDown, 
  FaSolidGear, 
  FaSolidCreditCard, 
  FaSolidRightFromBracket 
} from 'danx-icon';

const showMenu = ref(false);

const logout = () => {
  showMenu.value = false;
  // TODO: Implement logout logic
  console.log('Logout clicked');
};
</script>