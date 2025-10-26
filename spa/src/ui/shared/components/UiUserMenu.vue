<template>
  <div class="relative">
    <button
      class="flex items-center space-x-2 p-2 rounded-lg hover:bg-slate-100 transition-colors"
      @click="showMenu = !showMenu"
    >
      <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
        <FaSolidUser class="w-4 h-4 text-white" />
      </div>
      <FaSolidChevronDown class="w-3 h-3 text-slate-500" />
    </button>

    <div
      v-if="showMenu"
      class="absolute right-0 top-12 w-64 bg-white rounded-lg shadow-lg border border-slate-200 z-50"
    >
      <!-- User/Team Info Section -->
      <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
        <div class="font-semibold text-slate-900">{{ authUser?.name }}</div>
        <div class="text-sm text-slate-600">{{ authUser?.email }}</div>
        <div class="text-xs text-slate-500 mt-1">{{ authTeam?.name }}</div>
      </div>

      <!-- Team Switching Section (if multiple teams) -->
      <div v-if="authTeamList.length > 1" class="py-1">
        <div class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase">Teams</div>
        <button
          v-for="team in authTeamList"
          :key="team.id"
          class="w-full flex items-center px-4 py-2 text-sm hover:bg-slate-50 transition-colors"
          :class="authTeam?.id === team.id ? 'text-blue-600 font-semibold' : 'text-slate-700'"
          @click="onLogInToTeam(team)"
        >
          <FaSolidCheck v-if="authTeam?.id === team.id" class="w-3 h-3 mr-2" />
          <span :class="authTeam?.id !== team.id ? 'ml-5' : ''">{{ team.name }}</span>
        </button>
        <hr class="my-1 border-slate-200" />
      </div>

      <!-- Existing menu items -->
      <div class="py-1">
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
      </div>

      <hr class="my-1 border-slate-200" />

      <div class="py-1">
        <button
          class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"
          @click="onLogout"
        >
          <FaSolidRightFromBracket class="w-4 h-4 mr-3" />
          {{ isLoggingOut ? "Signing Out..." : "Sign Out" }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  FaSolidUser,
  FaSolidChevronDown,
  FaSolidGear,
  FaSolidCreditCard,
  FaSolidRightFromBracket,
  FaSolidCheck
} from 'danx-icon';
import { authUser, authTeam, authTeamList, loginToTeam } from '@/helpers/auth';
import { AuthTeam } from '@/types';

const router = useRouter();
const showMenu = ref(false);
const isLoggingIn = ref(false);
const isLoggingOut = ref(false);

function onLogout() {
  isLoggingOut.value = true;
  showMenu.value = false;
  router.push({ name: "auth.logout" });
  isLoggingOut.value = false;
}

async function onLogInToTeam(team: AuthTeam) {
  isLoggingIn.value = true;
  await loginToTeam(team);
  isLoggingIn.value = false;
  showMenu.value = false;
}
</script>