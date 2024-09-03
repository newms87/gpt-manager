import { ActionOptions, RenderedFormDialog, useActions, withDefaultActions } from "quasar-ui-danx";
import { h } from "vue";
import { dxTeamObject } from "./controls";
import { fields } from "./fields";
import { TeamObjectRoutes } from "./routes";


// This is the default action options for all items
const forAllItems: Partial<ActionOptions> = {
	onAction: TeamObjectRoutes.applyAction,
	onBatchAction: TeamObjectRoutes.batchAction
};

const excludes = ["create"];
const items: ActionOptions[] = [
	...withDefaultActions(dxTeamObject).filter(a => !excludes.includes(a.name)),
	{
		name: "create",
		label: "Create",
		vnode: (target, data) => h(RenderedFormDialog, {
			title: "Create " + data.type,
			contentClass: "w-96",
			form: { fields }
		}),
		onFinish: dxTeamObject.loadList
	}
];

export const { getAction, getActions, extendAction } = useActions(items, forAllItems);
