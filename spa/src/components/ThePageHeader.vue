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
						<div class="bg-sky-300">
							<div class="flex-x text-sky-600 font-bold text-base px-4 py-2">
								<TeamsIcon class="w-4 mr-2" />
								Teams
							</div>
							<div class="bg-sky-800">
								<div
									v-for="team in authTeamList"
									:key="team.id"
									@click="onLogInToTeam(team)"
								>
									<div v-if="authTeam.id === team.id" class="text-sky-300 flex-x gap-2 py-2 px-4">
										<AuthTeamIcon class="w-4" />
										{{ team.name }}
									</div>
									<div v-else class="cursor-pointer hover:bg-sky-900 py-2 px-4">
										{{ team.name }}
										<QTooltip>Log in to {{ team.name }}</QTooltip>
									</div>
								</div>
							</div>
						</div>
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
import { authTeam, authTeamList, authUser, loginToTeam } from "@/helpers";
import router from "@/router";
import { AuthTeam } from "@/types";
import { FaSolidUser as AccountIcon, FaSolidUserCheck as AuthTeamIcon, FaSolidUsers as TeamsIcon } from "danx-icon";
import { QToolbar, QToolbarTitle } from "quasar";
import { ref } from "vue";

const isLoggingIn = ref(false);
const isLoggingOut = ref(false);

function onLogout() {
	isLoggingOut.value = true;
	router.push({ name: "auth.logout" });
	isLoggingOut.value = false;
}

async function onLogInToTeam(team: AuthTeam) {
	isLoggingIn.value = true;
	await loginToTeam(team);
	isLoggingIn.value = false;
}
</script>
