import { getActions } from "@/components/Agents/agentActions";
import { AgentController } from "@/components/Agents/agentControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx/types";

export const columns: TableColumn[] = [
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		required: true,
		actionMenu: getActions({ menu: true }),
		onClick: (agent) => AgentController.activatePanel(agent, "edit")
	},
	{
		name: "api",
		label: "API",
		sortable: true,
		align: "left"
	},
	{
		name: "model",
		label: "Model",
		sortable: true,
		align: "left"
	},
	{
		name: "temperature",
		label: "Temperature",
		align: "left",
		sortable: true,
		required: true
	},
	{
		name: "thread_count",
		label: "Threads",
		align: "left",
		format: fNumber,
		onClick: (agent) => AgentController.activatePanel(agent, "threads")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
