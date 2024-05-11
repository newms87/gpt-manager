import { InputSourceRoutes } from "@/routes/inputSourceRoutes";
import { useListControls } from "quasar-ui-danx";
import { ActionController } from "quasar-ui-danx/types";

export const InputSourceController: ActionController = useListControls("input-sources", {
	label: "Input Sources",
	routes: InputSourceRoutes
});
