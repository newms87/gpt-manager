import router from "@/router";
import {
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as AuditsIcon,
	FaSolidFile as WorkflowInputsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidWorm as WorkflowsIcon
} from "danx-icon";

export default [
	{
		label: "Dashboard",
		icon: DashboardIcon,
		onClick: () => router.push({ name: "home" })
	},
	{
		label: "Workflow Inputs",
		icon: WorkflowInputsIcon,
		onClick: () => router.push({ name: "workflow-inputs" })
	},
	{
		label: "Workflows",
		icon: WorkflowsIcon,
		onClick: () => router.push({ name: "workflows" })
	},
	{
		label: "Agents",
		icon: AgentsIcon,
		onClick: () => router.push({ name: "agents" })
	},
	{
		label: "Audits",
		icon: AuditsIcon,
		onClick: () => router.push({ name: "audit-requests" })
	}
];
