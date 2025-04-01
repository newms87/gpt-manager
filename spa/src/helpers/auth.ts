import { dxTeam } from "@/components/Modules/Teams/config";
import { AuthTeam, AuthUser } from "@/types";
import { danxOptions, getItem, getUrlParam, setItem, sleep } from "quasar-ui-danx";
import { ref } from "vue";

const AUTH_TOKEN_KEY = "auth-token";
const AUTH_TEAM_LIST_KEY = "auth-team-list";
const AUTH_USER_KEY = "auth-user";
export const authToken = ref(getAuthToken() || "");
export const authTeam = ref<AuthTeam>(getAuthTeam());
export const authUser = ref<AuthUser>(getAuthUser());

// Set the Authorization header for all requests
if (isAuthenticated()) {
	danxOptions.value.request.headers.Authorization = `Bearer ${authToken.value}`;
} else if (!authTeam.value) {
	// Async resolve the desired team if not already set
	(async () => {
		authTeam.value = await loadAuthTeam(getUrlParam("team_id"));
	})();
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
 *  Attempt to resolve / load the authentication team based on the uuid passed in the query params or stored in local storage
 */
export async function loadAuthTeam(uuid: string = null): Promise<AuthTeam | null> {
	uuid = uuid || getUrlParam("team_id");

	let authTeam = getAuthTeam(uuid);
	if (authTeam) return authTeam;

	if (!uuid) return null;

	const teamList = await dxTeam.routes.list({ filter: { uuid } });

	// If the request was aborted, that means multiple calls were made, so wait a little bit and return the loaded value
	if (teamList.abort) {
		for (let i = 0; i < 50; i++) {
			await sleep(300);
			if ((authTeam = getAuthTeam(uuid))) {
				return authTeam;
			}
		}
		// If all else fails, try again
		return await loadAuthTeam(uuid);
	}
	if (teamList?.data?.length) {
		setAuthTeam(teamList.data[0]);
		return teamList.data[0];
	}
	return null;
}

/**
 * Get the team info from local storage (returns the first team if no uuid is provided)
 */
export function getAuthTeam(uuid: string = null): AuthTeam | null {
	uuid = uuid || getUrlParam("team_id");
	const teamList = getItem(AUTH_TEAM_LIST_KEY);

	if (teamList?.length) {
		if (uuid) {
			return teamList.find((team: AuthTeam) => team.uuid === uuid);
		}
		return teamList[0];
	}
}

/**
 * Set the authentication team in local storage
 */
export function setAuthTeam(team: AuthTeam) {
	const teamList = getItem(AUTH_TEAM_LIST_KEY) || [];

	if (!teamList.find((t: AuthTeam) => t.uuid === team.uuid)) {
		teamList.push(team);
		setItem(AUTH_TEAM_LIST_KEY, teamList);
	}

	authTeam.value = team;
}

/**
 * Get the authentication user from local storage
 */
export function getAuthUser(): AuthUser | null {
	return getItem(AUTH_USER_KEY);
}

/**
 * Set the authentication user in local storage
 */
export function setAuthUser(user: AuthUser) {
	setItem(AUTH_USER_KEY, user);
	authUser.value = user;
}
