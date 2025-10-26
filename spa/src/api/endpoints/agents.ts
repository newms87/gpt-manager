/**
 * Agent API Endpoints
 *
 * All agent-related API endpoints for managing AI agents, their threads,
 * and knowledge bases.
 */

import { buildApiUrl } from "../config";

export const agents = {
	/**
	 * Base agents endpoint - supports CRUD operations
	 * @endpoint /agents
	 * @supports GET (list), POST (create), PATCH (update), DELETE (delete)
	 */
	base: buildApiUrl("/agents"),

	/**
	 * Agent threads endpoint
	 * @endpoint /agent-threads
	 */
	agentThreads: buildApiUrl("/agent-threads"),

	/**
	 * Knowledge base endpoint
	 * @endpoint /knowledge
	 */
	knowledge: buildApiUrl("/knowledge"),

	/**
	 * MCP (Model Context Protocol) servers endpoint
	 * @endpoint /mcp-servers
	 */
	mcpServers: buildApiUrl("/mcp-servers"),

	/**
	 * Threads endpoint (generic threads, not agent-specific)
	 * @endpoint /threads
	 */
	threads: buildApiUrl("/threads"),
} as const;
