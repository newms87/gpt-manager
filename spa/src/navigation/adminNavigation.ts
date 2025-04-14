import {
	FaRegularFileLines as PromptsIcon,
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as SchemaDefinitionsIcon,
	FaSolidDownload as WorkflowInputsIcon,
	FaSolidPallet as WorkflowDefinitionsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidToiletPaperSlash as AuditsIcon
} from "danx-icon";

export default [
	{
		label: "Dashboard",
		icon: DashboardIcon,
		route: { name: "home" }
	},
	{
		label: "Workflow Inputs",
		icon: WorkflowInputsIcon,
		route: { name: "workflow-inputs" }
	},
	{
		label: "Workflow Definitions",
		icon: WorkflowDefinitionsIcon,
		route: { name: "workflow-definitions" }
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
