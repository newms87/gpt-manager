import { McpServerInfoPanel } from "@/components/Modules/McpServers/Panels";
import { McpServer } from "@/types";
import { h } from "vue";

export const panels = [
	{
		name: "edit",
		label: "Details",
		vnode: (mcpServer: McpServer) => h(McpServerInfoPanel, { mcpServer })
	}
];