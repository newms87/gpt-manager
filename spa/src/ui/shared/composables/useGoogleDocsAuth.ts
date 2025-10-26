import { apiUrls } from "@/api";
import { ref } from "vue";
import { request } from "quasar-ui-danx";

// Types
interface OAuthValidationResponse {
	valid: boolean;
	reason: string;
	token?: {
		created_at: string;
		[key: string]: any;
	};
}

// Module-level state (shared across all instances)
const isAuthorized = ref(false);
const isValidating = ref(false);
const error = ref<string | null>(null);
const authDate = ref<string | null>(null);
const tokenExpired = ref(false);

// Track if we've initialized
let hasInitialized = false;

/**
 * Shared composable for Google Docs OAuth validation state
 * Uses module-level reactive state to ensure all components share the same auth status
 */
export function useGoogleDocsAuth() {
	const validateAuth = async (): Promise<void> => {
		try {
			isValidating.value = true;
			error.value = null;
			tokenExpired.value = false;

			const response: OAuthValidationResponse = await request.post(apiUrls.oauth.googleValidate);

			if (response.valid) {
				isAuthorized.value = true;
				authDate.value = response.token?.created_at || null;
				error.value = null;
				tokenExpired.value = false;
			} else {
				// Invalid token - this is an expected state, not an error
				isAuthorized.value = false;
				authDate.value = null;
				error.value = null; // Clear error - this is expected

				// Set tokenExpired flag if the reason is 'expired'
				tokenExpired.value = response.reason === 'expired';
			}
		} catch (err) {
			// Only network/server errors end up here - these ARE unexpected errors
			console.error("Error validating Google Docs auth:", err);
			error.value = "Unable to check Google Docs connection. Please try again.";
			isAuthorized.value = false;
			authDate.value = null;
			tokenExpired.value = false;
		} finally {
			isValidating.value = false;
		}
	};

	// Auto-initialize on first use
	if (!hasInitialized) {
		hasInitialized = true;
		validateAuth();
	}

	return {
		isAuthorized,
		isValidating,
		error,
		authDate,
		tokenExpired,
		validateAuth
	};
}
