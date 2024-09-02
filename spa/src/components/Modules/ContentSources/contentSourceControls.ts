import { ContentSourceRoutes } from "@/routes/contentSourceRoutes";
import { ActionController, useListControls } from "quasar-ui-danx";

export const dxContentSource: ActionController = useListControls("content-sources", {
	label: "Content Sources",
	routes: ContentSourceRoutes
});
