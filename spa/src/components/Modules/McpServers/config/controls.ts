import { McpServer } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("mcp-servers", {
	label: "MCP Servers",
	routes
}) as ListController<McpServer>;