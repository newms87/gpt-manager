import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { routes } from "./routes";

const actions: ActionOptions[] = [
    ...withDefaultActions("Artifact")
];

export const actionControls = useActions(actions, { routes });
export const menuActions = actionControls.getActions(["copy", "edit", "delete"]);
export const batchActions = actionControls.getActions(["delete"]);
