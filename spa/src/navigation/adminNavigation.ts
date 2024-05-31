import router from "@/router";
import {
	FaSolidCloudBolt as DashboardIcon,
	FaSolidDatabase as AuditsIcon,
	FaSolidDownload as WorkflowInputsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidTableCells as ContentSourcesIcon,
	FaSolidWorm as WorkflowsIcon
} from "danx-icon";

export default [
	{
		label: "Dashboard",
		icon: DashboardIcon,
		onClick: () => router.push({ name: "home" })
	},
	{
		label: "Content Sources",
		icon: ContentSourcesIcon,
		onClick: () => router.push({ name: "content-sources" })
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
