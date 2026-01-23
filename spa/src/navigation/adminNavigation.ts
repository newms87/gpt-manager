import { openDebugPanel } from "@/composables/usePusherDebug";
import { authUser } from "@/helpers/auth";
import {
	FaRegularFileLines as PromptsIcon,
	FaSolidCloudBolt as DashboardIcon,
	FaSolidCode as DeveloperToolsIcon,
	FaSolidDownload as WorkflowInputsIcon,
	FaSolidPallet as WorkflowDefinitionsIcon,
	FaSolidRobot as AgentsIcon,
	FaSolidToiletPaperSlash as AuditsIcon
} from "danx-icon";
import { computed } from "vue";

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
	},
	{
		label: "Developer Tools",
		icon: DeveloperToolsIcon,
		onClick: () => openDebugPanel(),
		hidden: computed(() => !authUser.value?.can?.viewDeveloperTools)
	}
];
