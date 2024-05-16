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
							<TextField v-model="input.username" label="Email" @keyup.enter="onNext" />
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
							<QBtn class="bg-sky-800 text-sky-200 w-full" @click="onLogin">Log In</QBtn>
						</div>
					</div>
				</QCardSection>
			</QCard>
		</div>
	</ThePrimaryLayout>
</template>
<script setup lang="ts">
import ThePrimaryLayout from "@/components/Layouts/ThePrimaryLayout";
import { AuthRoutes } from "@/routes/authRoutes";
import { TextField } from "quasar-ui-danx";
import { ref } from "vue";

const input = ref({
	username: "",
	password: ""
});

const isLoggingIn = ref(false);
const passwordField = ref(null);
async function onLogin() {
	isLoggingIn.value = true;
	await AuthRoutes.login(input.value);
	isLoggingIn.value = false;
}
function onNext() {
	console.log("pas", passwordField.value);
	// Focus the <input /> field inside the passwordField ref
	passwordField.value.querySelector("input").focus();
}
</script>
