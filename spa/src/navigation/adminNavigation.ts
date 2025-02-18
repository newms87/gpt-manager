import {
	FaRegularFileLines as PromptsIcon,
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as SchemaDefinitionsIcon,
	FaSolidDownload as WorkflowInputsIcon,
	FaSolidGear as TaskDefinitionsIcon,
	FaSolidPallet as TaskWorkflowsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidTableCells as ContentSourcesIcon,
	FaSolidToiletPaperSlash as AuditsIcon,
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
		label: "Task Workflows",
		icon: TaskWorkflowsIcon,
		route: { name: "task-workflows" }
	},
	{
		label: "Task Definitions",
		icon: TaskDefinitionsIcon,
		route: { name: "task-definitions" }
	},
	{
		label: "Schema Definitions",
		icon: SchemaDefinitionsIcon,
		route: { name: "schema-definitions" }
	},
	{
		label: "Prompt Directives",
		icon: PromptsIcon,
		route: { name: "prompt-directives" }
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
