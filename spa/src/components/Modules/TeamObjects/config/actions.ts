import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ActionOptions, RenderedFormDialog, useActions, withDefaultActions } from "quasar-ui-danx";
import { h } from "vue";
import { controls } from "./controls";
import { fields } from "./fields";
import { routes } from "./routes";

export const actions: ActionOptions[] = [
	...withDefaultActions("Object", controls)
];

export const actionControls = useActions(actions, { routes, controls });

actionControls.modifyAction("create", {
	vnode: (target, data) => h(RenderedFormDialog, {
		title: "Create " + data.type,
		contentClass: "w-96",
		form: { fields }
	}),
	onFinish: controls.loadList
});

actionControls.modifyAction("edit", {
	alias: "update",
	onAction: routes.applyAction,
	vnode: (target: TeamObject) => h(RenderedFormDialog, {
		title: "Edit " + target.name,
		contentClass: "w-96",
		confirmText: "Save",
		form: { fields },
		modelValue: target
	}),
	onFinish: controls.loadList
});
