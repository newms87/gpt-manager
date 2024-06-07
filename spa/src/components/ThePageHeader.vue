<template>
	<QToolbar>
		<QToolbarTitle class="bg-sky-800 flex items-center">
			<div v-if="siteSettings.logo">
				<LogoImage :src="siteSettings.logo" class="w-16 h-16" />
			</div>
			<div class="pl-3 py-4 flex-grow">{{ siteSettings.name }}</div>
			<div class="px-3 flex items-center flex-nowrap">
				<div class="mr-4">
					<div v-if="authUser" class="text-sm">{{ authUser.email }}</div>
					<div class="text-xs text-slate-400">{{ authTeam }}</div>
				</div>
				<QBtn class="bg-sky-950 px-1 py-3 shadow-2" round>
					<AccountIcon class="w-4" />

					<QMenu>
						<a
							v-ripple
							class="p-3 block hover:bg-slate-600 text-slate-300"
							@click="onLogout"
						>
							{{ isLoggingOut ? "Logging Out..." : "Log Out" }}
						</a>
					</QMenu>
				</QBtn>
			</div>
		</QToolbarTitle>
	</QToolbar>
</template>
<script setup lang="ts">
import LogoImage from "@/components/Shared/Images/LogoImage";
import { siteSettings } from "@/config";
import { authTeam, authUser } from "@/helpers";
import router from "@/router";
import { FaSolidUser as AccountIcon } from "danx-icon";
import { QToolbar, QToolbarTitle } from "quasar";
import { ref } from "vue";

const isLoggingOut = ref(false);
function onLogout() {
	isLoggingOut.value = true;
	router.push({ name: "auth.logout" });
	isLoggingOut.value = false;
}
</script>
