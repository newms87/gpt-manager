/**
 * Artifact API Endpoints
 *
 * All artifact-related API endpoints for managing workflow
 * artifacts and stored files.
 */

import { buildApiUrl } from "../config";

export const artifacts = {
	/**
	 * Artifacts endpoint
	 * @endpoint /artifacts
	 */
	base: buildApiUrl("/artifacts"),

	/**
	 * Artifact stored files endpoint
	 * @endpoint /artifact-stored-files
	 */
	storedFiles: buildApiUrl("/artifact-stored-files"),
} as const;
