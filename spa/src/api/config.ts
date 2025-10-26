/**
 * Base API configuration utilities
 *
 * This module provides the foundational utilities for building API URLs
 * throughout the application. All API endpoint definitions should use
 * these utilities to ensure consistency and type safety.
 */

/**
 * Base API URL from environment configuration
 */
export const API_BASE_URL = import.meta.env.VITE_API_URL;

/**
 * Build a complete API URL from a path
 *
 * @param path - The API endpoint path (e.g., "/login", "/agents")
 * @returns Complete API URL
 *
 * @example
 * ```ts
 * const loginUrl = buildApiUrl("/login");
 * // Returns: "https://api.example.com/login"
 * ```
 */
export function buildApiUrl(path: string): string {
	return `${API_BASE_URL}${path}`;
}

/**
 * Create a URL builder function for endpoints with dynamic parameters
 *
 * @param template - URL template function that receives typed parameters
 * @returns Function that builds URLs with the provided parameters
 *
 * @example
 * ```ts
 * interface TeamParams {
 *   teamId: number;
 * }
 *
 * const getTeamUrl = createUrlBuilder<TeamParams>(
 *   (params) => `/teams/${params.teamId}`
 * );
 *
 * const url = getTeamUrl({ teamId: 123 });
 * // Returns: "https://api.example.com/teams/123"
 * ```
 */
export function createUrlBuilder<TParams = void>(
	template: (params: TParams) => string
): (params: TParams) => string {
	return (params: TParams) => buildApiUrl(template(params));
}
