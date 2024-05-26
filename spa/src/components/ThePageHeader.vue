<template>
	<QToolbar>
		<QToolbarTitle class="bg-sky-800 py-2 flex items-center">
			<div class="pl-3 flex-grow">Sage Sweeper</div>
			<div class="px-3">
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
