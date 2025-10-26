/**
 * File Upload API Endpoints
 *
 * All file upload-related API endpoints for managing
 * file uploads and stored files.
 */

import { buildApiUrl } from "../config";

export const fileUpload = {
	/**
	 * Stored files endpoint
	 * @endpoint /stored-files
	 */
	storedFiles: buildApiUrl("/stored-files"),

	/**
	 * File upload endpoint
	 * @endpoint POST /stored-files/upload
	 */
	upload: buildApiUrl("/stored-files/upload"),
} as const;
