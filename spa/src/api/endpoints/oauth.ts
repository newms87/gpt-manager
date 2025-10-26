/**
 * OAuth API Endpoints
 *
 * All OAuth-related API endpoints for managing third-party
 * integrations like Google Docs.
 */

import { buildApiUrl } from "../config";

export const oauth = {
	/**
	 * Google Docs OAuth endpoint
	 * @endpoint POST /google-docs/oauth
	 */
	googleDocsOAuth: buildApiUrl("/google-docs/oauth"),

	/**
	 * Google Docs OAuth callback endpoint
	 * @endpoint GET /google-docs/oauth/callback
	 */
	googleDocsOAuthCallback: buildApiUrl("/google-docs/oauth/callback"),

	/**
	 * Google Docs disconnect endpoint
	 * @endpoint POST /google-docs/disconnect
	 */
	googleDocsDisconnect: buildApiUrl("/google-docs/disconnect"),

	/**
	 * Google OAuth validate endpoint
	 * @endpoint POST /oauth/google/validate
	 */
	googleValidate: buildApiUrl("/oauth/google/validate"),

	/**
	 * Google OAuth authorize endpoint
	 * @endpoint GET /oauth/google/authorize
	 */
	googleAuthorize: buildApiUrl("/oauth/google/authorize"),
} as const;
