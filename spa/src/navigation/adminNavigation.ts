import router from "@/router";
import {
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as AuditsIcon,
	FaSolidFile as InputSourcesIcon,
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
		label: "Input Sources",
		icon: InputSourcesIcon,
		onClick: () => router.push({ name: "input-sources" })
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
