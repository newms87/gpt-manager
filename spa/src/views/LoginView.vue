<template>
	<ThePrimaryLayout>
		<div class="flex items-center justify-center h-full">
			<QCard class="bg-slate-700 p-6">
				<QCardSection>
					<div class="pb-3">
						<h4>Sage Sweeper Log In</h4>
					</div>
					<div class="mt-6">
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
								:disable="isLoggingIn"
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
	</ThePrimaryLayout>
</template>
<script setup lang="ts">
import ThePrimaryLayout from "@/components/Layouts/ThePrimaryLayout";
import { setAuthToken } from "@/helpers/auth";
import { AuthRoutes } from "@/routes/authRoutes";
import { TextField } from "quasar-ui-danx";
import { ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const input = ref({
	email: "",
	password: ""
});

const isLoggingIn = ref(false);
const passwordField = ref(null);
const errorMsg = ref("");
async function onLogin() {
	isLoggingIn.value = true;
	const result = await AuthRoutes.login(input.value);

	if (result.error) {
		errorMsg.value = result.error;
	} else {
		setAuthToken(result.token);
		await router.push({ name: "home" });
	}
	isLoggingIn.value = false;
}
function onNext() {
	passwordField.value.querySelector("input").focus();
}
</script>
