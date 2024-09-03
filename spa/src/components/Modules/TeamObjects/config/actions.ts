import { ActionOptions, RenderedFormDialog, useActions, withDefaultActions } from "quasar-ui-danx";
import { h } from "vue";
import { controls } from "./controls";
import { fields } from "./fields";
import { routes } from "./routes";

const excludes = ["create", "edit"];
export const actions: ActionOptions[] = [
	...withDefaultActions(controls).filter(a => !excludes.includes(a.name)),
	{
		name: "create",
		label: "Create",
		vnode: (target, data) => h(RenderedFormDialog, {
			title: "Create " + data.type,
			contentClass: "w-96",
			form: { fields }
		}),
		onFinish: controls.loadList
	},
	{
		name: "edit",
		alias: "update",
		label: "Edit",
		vnode: (target, data) => h(RenderedFormDialog, {
			title: "Edit " + data.type + " " + target.name,
			contentClass: "w-96",
			form: { fields }
		}),
		onFinish: controls.loadList
	}
];

export const actionControls = useActions(actions, { routes });
