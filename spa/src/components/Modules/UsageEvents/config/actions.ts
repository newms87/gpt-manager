import { ActionOptions, useActions, withDefaultActions } from "quasar-ui-danx";
import { controls } from "./controls";
import { routes } from "./routes";

export const actions: ActionOptions[] = withDefaultActions("Usage Event", controls);
export const actionControls = useActions(actions, { routes });
export const menuActions = actionControls.getActions(["view"]);
export const batchActions = actionControls.getActions([]);