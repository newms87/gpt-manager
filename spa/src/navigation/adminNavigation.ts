import {
	FaRegularFileLines as PromptsIcon,
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as AuditsIcon,
	FaSolidDownload as WorkflowInputsIcon,
	FaSolidGear as TaskDefinitionsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidTableCells as ContentSourcesIcon,
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
		label: "Task Definitions",
		icon: TaskDefinitionsIcon,
		route: { name: "task-definitions" }
	},
	{
		label: "Prompts",
		icon: PromptsIcon,
		route: { name: "prompts" },
		children: [
			{
				label: "Directives",
				route: { name: "prompts.directives" }
			},
			{
				label: "Schemas",
				route: { name: "prompts.schemas" }
			}
		]
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
