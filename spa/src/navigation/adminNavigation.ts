import {
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as AuditsIcon,
	FaSolidDownload as WorkflowInputsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidTableCells as ContentSourcesIcon,
	FaSolidTextSlash as PromptsIcon,
	FaSolidWorm as WorkflowsIcon
} from "danx-icon";

export default [
	{
		label: "Dashboard",
		icon: DashboardIcon,
		route: { name: "home" }
	},
	{
		label: "Content Sources",
		icon: ContentSourcesIcon,
		route: { name: "content-sources" }
	},
	{
		label: "Workflow Inputs",
		icon: WorkflowInputsIcon,
		route: { name: "workflow-inputs" }
	},
	{
		label: "Workflows",
		icon: WorkflowsIcon,
		route: { name: "workflows" }
	},
	{
		label: "Prompts",
		icon: PromptsIcon,
		route: { name: "prompts" }
	},
	{
		label: "Agents",
		icon: AgentsIcon,
		route: { name: "agents" }
	},
	{
		label: "Audits",
		icon: AuditsIcon,
		route: { name: "audit-requests" }
	}
];
