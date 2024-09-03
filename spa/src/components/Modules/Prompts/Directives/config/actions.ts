import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { dxPromptDirective } from "./controls";
import { PromptDirectiveRoutes } from "./routes";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: PromptDirectiveRoutes.applyAction
};

const items: ActionOptions[] = [
	...withDefaultActions(dxPromptDirective)
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
