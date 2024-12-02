import { TeamObjectWorkflowsPanel } from "@/components/Modules/TeamObjects/Panels";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ActionPanel, BadgeTab } from "quasar-ui-danx";
import { h } from "vue";

export const panels: ActionPanel[] = [
	{
		name: "workflows",
		label: "Workflows",
		tabVnode: (teamObject: TeamObject) => h(BadgeTab, { count: teamObject.workflow_inputs_count || 0 }),
		vnode: (teamObject: TeamObject) => h(TeamObjectWorkflowsPanel, { teamObject })
	}
];
