import { useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("demand-templates", {
	label: "Templates",
	routes
});