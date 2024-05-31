import { ContentSourceRoutes } from "@/routes/contentSourceRoutes";
import { useListControls } from "quasar-ui-danx";
import { ActionController } from "quasar-ui-danx/types";

export const ContentSourceController: ActionController = useListControls("content-sources", {
	label: "Content Sources",
	routes: ContentSourceRoutes
});
