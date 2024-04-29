import { filterActions } from "@/components/Agents/agentsActions";
import { AgentController } from "@/components/Agents/agentsControls";
import { fDate } from "quasar-ui-danx";

export const columns = [
	{
		name: "name",
		label: "Name",
		field: "name",
		align: "left",
		sortable: true,
		required: true,
		actionMenu: filterActions({ menu: true }),
		onClick: (agent) => AgentController.activatePanel(agent, "edit")
	},
	{
		name: "model",
		label: "Model",
		field: "model",
		sortable: true,
		align: "left"
	},
	{
		name: "temperature",
		label: "Temperature",
		field: "temperature",
		align: "left",
		sortable: true,
		required: true
	},
	{
		name: "description",
		label: "Description",
		field: "description",
		sortable: true,
		align: "left"
	},
	{
		name: "created_at",
		label: "Created Date",
		field: "created_at",
		sortable: true,
		align: "left",
		format: fDate
	}
];
