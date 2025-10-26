<template>
    <div :class="compact ? '' : 'google-docs-auth'">
        <!-- Loading State -->
        <div v-if="isValidating" class="flex items-center space-x-2 text-gray-500">
            <div class="w-4 h-4 border-2 border-gray-300 border-t-blue-500 rounded-full animate-spin"></div>
            <span class="text-sm">Checking Google Docs...</span>
        </div>

        <!-- Authorized State -->
        <div v-else-if="isAuthorized" :class="compact ? 'inline-flex items-center space-x-2' : 'space-y-1'">
            <div class="flex items-center space-x-2 text-green-600">
                <FaSolidCheck class="w-4 h-4" />
                <span class="text-sm font-medium">Linked to Google Docs</span>
                <button
                    :disabled="isConnecting"
                    class="ml-2 p-1 text-gray-400 hover:text-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="handleConnect"
                    title="Refresh Google Docs token"
                >
                    <FaSolidArrowsRotate class="w-3 h-3" :class="{ 'animate-spin': isConnecting }" />
                </button>
            </div>
            <div v-if="authDate && !compact" class="text-xs text-gray-500 ml-6">
                Connected {{ formattedAuthDate }}
            </div>
        </div>

        <!-- Error State (unexpected errors only) -->
        <div v-else-if="error" class="space-y-2">
            <div class="flex items-center space-x-2 text-red-500 text-sm">
                <FaSolidTriangleExclamation class="w-4 h-4" />
                <span>{{ error }}</span>
            </div>
            <!-- Still show connect button even on error -->
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

        <!-- Unauthorized State (no token or expired) -->
        <div v-else class="space-y-2">
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
            <!-- Optional note if token is expired -->
            <div v-if="tokenExpired && !compact" class="text-xs text-gray-500">
                Connection expired - please reconnect
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { FaSolidArrowsRotate, FaSolidCheck, FaSolidLink, FaSolidTriangleExclamation } from "danx-icon";
import { fTimeAgo, request } from "quasar-ui-danx";
import { computed, ref } from "vue";
import { useGoogleDocsAuth } from "../composables/useGoogleDocsAuth";

// Props
withDefaults(defineProps<{
    compact?: boolean;
}>(), {
    compact: false
});

// Types
interface OAuthAuthorizeResponse {
    authorization_url: string;
    service: string;
    state: string;
}

// Use shared auth state composable
const { isAuthorized, isValidating, error, authDate, tokenExpired, validateAuth } = useGoogleDocsAuth();

// Local state
const isConnecting = ref(false);

// Computed
const formattedAuthDate = computed(() => fTimeAgo(authDate.value));

// Methods
async function handleConnect(): Promise<void> {
    try {
        isConnecting.value = true;
        error.value = null;

        const redirectUrl = window.location.href;

        const authData: OAuthAuthorizeResponse = await request.get("oauth/google/authorize", {
            params: {
                redirect_after_auth: redirectUrl
            }
        });

        // Redirect to Google OAuth
        window.location.href = authData.authorization_url;
    } catch (err) {
        console.error("Error initiating OAuth:", err);
        error.value = "Failed to connect to Google Docs";
        isConnecting.value = false;
    }
}
</script>

<style lang="scss" scoped>
.google-docs-auth {
    @apply p-2;
}
</style>
