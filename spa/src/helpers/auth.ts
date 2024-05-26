import { danxOptions, getItem, setItem } from "quasar-ui-danx";
import { ref } from "vue";

const AUTH_TOKEN_KEY = "auth-token";
const AUTH_TEAM_KEY = "auth-team";
export const authToken = ref(getAuthToken() || "");
export const authTeam = ref(getAuthTeam() || "");

// Set the Authorization header for all requests
if (isAuthenticated()) {
	danxOptions.value.request.headers.Authorization = `Bearer ${authToken.value}`;
}

/**
 * Check if the user is authenticated via the token stored in local storage
 */
export function isAuthenticated() {
	return !!getAuthToken();
}

/**
 * Get the authentication token from local storage
 */
export function getAuthToken() {
	return getItem(AUTH_TOKEN_KEY);
}

/**
 * Set the authentication token in local storage
 */
export function setAuthToken(token: string) {
	setItem(AUTH_TOKEN_KEY, token);
	authToken.value = token;
	danxOptions.value.request.headers.Authorization = `Bearer ${token}`;
}

/**
 * Get the authentication team from local storage
 */
export function getAuthTeam() {
	// Check URL ?team= query parameter
	const urlParams = new URLSearchParams(window.location.search);
	const team = urlParams.get("team");
	return team || getItem(AUTH_TEAM_KEY);
}

/**
 * Set the authentication team in local storage
 */
export function setAuthTeam(team: string) {
	setItem(AUTH_TEAM_KEY, team);
	authTeam.value = team;
}
