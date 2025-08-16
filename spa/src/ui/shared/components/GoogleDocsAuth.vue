<template>
	<div class="google-docs-auth">
		<!-- Loading State -->
		<div v-if="isLoading" class="flex items-center space-x-2 text-gray-500">
			<div class="w-4 h-4 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin"></div>
			<span class="text-sm">Checking Google Docs...</span>
		</div>

		<!-- Error State -->
		<div v-else-if="error" class="text-red-500 text-sm">
			<div class="flex items-center space-x-2">
				<FaSolidTriangleExclamation class="w-4 h-4" />
				<span>Error checking Google Docs</span>
			</div>
		</div>

		<!-- Authorized State -->
		<div v-else-if="isAuthorized" class="space-y-1">
			<div class="flex items-center space-x-2 text-green-600">
				<FaSolidCheck class="w-4 h-4" />
				<span class="text-sm font-medium">Linked to Google Docs</span>
			</div>
			<div v-if="authDate" class="text-xs text-gray-500 ml-6">
				Connected {{ formattedAuthDate }}
			</div>
		</div>

		<!-- Unauthorized State -->
		<div v-else>
			<button
				:disabled="isConnecting"
				class="flex items-center space-x-2 text-blue-600 hover:text-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
				@click="handleConnect"
			>
				<FaSolidLink class="w-4 h-4" />
				<span class="text-sm font-medium">
          {{ isConnecting ? "Connecting..." : "Connect Google Docs" }}
        </span>
			</button>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidLink, FaSolidTriangleExclamation } from "danx-icon";
import { request } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

// Types
interface OAuthStatusResponse {
	has_token: boolean;
	is_configured: boolean;
	service: string;
	token?: {
		created_at: string;
		[key: string]: any;
	};
}

interface OAuthAuthorizeResponse {
	authorization_url: string;
	service: string;
	state: string;
}

// State
const isLoading = ref(true);
const isConnecting = ref(false);
const error = ref<string | null>(null);
const oauthStatus = ref<OAuthStatusResponse | null>(null);

// Computed
const isAuthorized = computed(() =>
	oauthStatus.value?.has_token && oauthStatus.value?.is_configured
);

const authDate = computed(() =>
	oauthStatus.value?.token?.created_at
);

const formattedAuthDate = computed(() => {
	if (!authDate.value) return "";

	const date = new Date(authDate.value);
	const now = new Date();
	const diffTime = Math.abs(now.getTime() - date.getTime());
	const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

	if (diffDays === 1) {
		return "yesterday";
	} else if (diffDays < 7) {
		return `${diffDays} days ago`;
	} else if (diffDays < 30) {
		const weeks = Math.floor(diffDays / 7);
		return weeks === 1 ? "1 week ago" : `${weeks} weeks ago`;
	} else {
		return date.toLocaleDateString("en-US", {
			year: "numeric",
			month: "short",
			day: "numeric"
		});
	}
});

// Methods
async function checkOAuthStatus(): Promise<void> {
	try {
		isLoading.value = true;
		error.value = null;

		console.log("Checking Google OAuth status...");
		const response = await request.get("oauth/google/status");
		console.log("OAuth status response:", response);
		oauthStatus.value = response;
	} catch (err) {
		console.error("Error checking OAuth status:", err);
		error.value = "Failed to check Google Docs connection";
	} finally {
		isLoading.value = false;
	}
}

async function handleConnect(): Promise<void> {
	try {
		isConnecting.value = true;
		error.value = null;

		const redirectUrl = `${window.location.origin}/ui/templates`;
		console.log("Initiating OAuth with redirect URL:", redirectUrl);

		const response = await request.get("oauth/google/authorize", {
			params: {
				redirect_after_auth: redirectUrl
			}
		});

		const authData: OAuthAuthorizeResponse = response;
		console.log("OAuth authorization response:", authData);

		// Redirect to Google OAuth
		window.location.href = authData.authorization_url;
	} catch (err) {
		console.error("Error initiating OAuth:", err);
		error.value = "Failed to connect to Google Docs";
		isConnecting.value = false;
	}
}

// Lifecycle
onMounted(() => {
	checkOAuthStatus();
});
</script>

<style lang="scss" scoped>
.google-docs-auth {
	@apply p-2;
}
</style>
