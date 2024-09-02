import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { dxTeamObject } from "./controls";
import { TeamObjectRoutes } from "./routes";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: TeamObjectRoutes.applyAction,
	onBatchAction: TeamObjectRoutes.batchAction
};

const items: ActionOptions[] = [
	...withDefaultActions("Team Objects", dxTeamObject)
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
