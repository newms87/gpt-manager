/**
 * Authentication API Endpoints
 *
 * All authentication-related API endpoints including login, logout,
 * and team switching functionality.
 */

import { buildApiUrl } from "../config";

export const auth = {
	/**
	 * Login endpoint
	 * @endpoint POST /login
	 */
	login: buildApiUrl("/login"),

	/**
	 * Login to specific team endpoint
	 * @endpoint POST /login-to-team
	 */
	loginToTeam: buildApiUrl("/login-to-team"),

	/**
	 * Logout endpoint
	 * @endpoint POST /logout
	 */
	logout: buildApiUrl("/logout"),

	/**
	 * Broadcasting authentication endpoint for Pusher
	 * @endpoint POST /broadcasting/auth
	 */
	broadcastingAuth: buildApiUrl("/broadcasting/auth"),
} as const;
