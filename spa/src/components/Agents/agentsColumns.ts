import { filterActions } from "@/components/Agents/agentsActions";
import { AgentController } from "@/components/Agents/agentsControls";
import { fDate } from "quasar-ui-danx";

export const columns = [
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		required: true,
		actionMenu: filterActions({ menu: true }),
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
		name: "threads",
		label: "Threads",
		align: "left",
		format: (threads) => threads?.length || 0,
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
