<template>
	<QToolbar>
		<QToolbarTitle class="bg-sky-800 flex items-center">
			<div v-if="authTeam?.logo">
				<LogoImage :src="authTeam.logo" class="h-16" image-class="max-h-full" />
			</div>
			<div class="pl-3 py-4 flex-grow">{{ authTeam?.name || "GPT Manager" }}</div>
			<div class="px-3 flex-x">
				<div class="mr-4">
					<div v-if="authUser" class="text-sm">{{ authUser.email }}</div>
					<div v-if="authTeam" class="text-xs text-slate-400">{{ authTeam.name }}</div>
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
import { LogoImage } from "@/components/Shared";
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
