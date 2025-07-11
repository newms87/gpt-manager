import { fDate, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

export const columns: TableColumn[] = [
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: (mcpServer) => controls.activatePanel(mcpServer, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (mcpServer) => controls.activatePanel(mcpServer, "edit")
	},
	{
		name: "label",
		label: "Label",
		align: "left",
		sortable: true
	},
	{
		name: "server_url",
		label: "Server URL",
		align: "left",
		sortable: true
	},
	{
		name: "require_approval",
		label: "Approval",
		align: "center",
		sortable: true
	},
	{
		name: "is_active",
		label: "Active",
		align: "center",
		sortable: true,
		format: (value: boolean) => value ? "Yes" : "No"
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		format: fDate
	}
];