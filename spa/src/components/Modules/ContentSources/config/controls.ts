import { ContentSource } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("content-sources", {
	label: "Content Sources",
	routes
}) as ListController<ContentSource>;
