import { Agent } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("agents", {
	label: "Agents",
	routes
}) as ListController<Agent>;
