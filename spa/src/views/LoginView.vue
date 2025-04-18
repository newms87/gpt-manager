<template>
	<div class="flex items-center justify-center h-full">
		<QCard class="bg-slate-700 p-6 min-w-96">
			<QCardSection>
				<div class="pb-3">
					<h4>Log In</h4>
					<SelectTeamMenu v-if="authTeamList.length > 0" v-model="authTeam" class="mt-4" />
				</div>
				<div class="mt-3">
					<div>
						<TextField v-model="input.email" label="Email" @keyup.enter="onNext" />
					</div>
					<div ref="passwordField" class="mt-3">
						<TextField
							v-model="input.password"
							label="Password"
							type="password"
							@keyup.enter="onLogin"
						/>
					</div>

					<div class="mt-4">
						<QBtn
							class="bg-sky-800 text-sky-200 w-full"
							:loading="isLoggingIn"
							@click="onLogin"
						>Log In
						</QBtn>
					</div>
					<div class="mt-3">
						<QBanner v-if="errorMsg" class="bg-red-800 text-red-300 rounded">{{ errorMsg }}</QBanner>
					</div>
				</div>
			</QCardSection>
		</QCard>
	</div>
</template>
<script setup lang="ts">
import SelectTeamMenu from "@/components/Modules/Teams/SelectTeamMenu";
import {
	authTeam,
	authTeamList,
	loadAuthTeam,
	setAuthTeam,
	setAuthTeamList,
	setAuthToken,
	setAuthUser
} from "@/helpers/auth";
import { AuthRoutes } from "@/routes/authRoutes";
import { TextField } from "quasar-ui-danx";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const input = ref({
	email: "",
	password: ""
});

onMounted(loadAuthTeam);

const isLoggingIn = ref(false);
const passwordField = ref(null);
const errorMsg = ref("");

async function onLogin() {
	isLoggingIn.value = true;
	const result = await AuthRoutes.login({ ...input.value, team_uuid: authTeam.value?.uuid });

	if (!result || result.error) {
		if (!result || result.exception) {
			errorMsg.value = "An error occurred. Please try again.";
		} else {
			errorMsg.value = (typeof result.error === "string" ? result.error : result.message) || "An unexpected error occurred.";
		}
	} else {
		setAuthToken(result.token);
		if (result.team) {
			setAuthTeam(result.team);
		}
		if (result.user) {
			setAuthUser(result.user);
		}
		if (result.authTeamList) {
			setAuthTeamList(result.authTeamList);
		}
		await router.push({ name: "home" });
	}
	isLoggingIn.value = false;
}
function onNext() {
	passwordField.value.querySelector("input").focus();
}
</script>
