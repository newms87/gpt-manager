import { getActions } from "@/components/Modules/Agents/config/actions";
import { dxAgent } from "@/components/Modules/Agents/config/controls";
import { fDate, fNumber, TableColumn } from "quasar-ui-danx";

export const columns: TableColumn[] = [
	{
		name: "menu",
		label: "",
		required: true,
		hideContent: true,
		shrink: true,
		actionMenu: getActions({ menu: true })
	},
	{
		name: "id",
		label: "ID",
		align: "left",
		sortable: true,
		shrink: true,
		onClick: (agent) => dxAgent.activatePanel(agent, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (agent) => dxAgent.activatePanel(agent, "edit")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
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
		sortable: true
	},
	{
		name: "threads_count",
		label: "Threads",
		format: fNumber,
		sortable: true,
		onClick: (agent) => dxAgent.activatePanel(agent, "threads")
	},
	{
		name: "assignments_count",
		label: "Assignments",
		format: fNumber,
		sortable: true,
		onClick: (agent) => dxAgent.activatePanel(agent, "assignments")
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		format: fDate
	}
];
