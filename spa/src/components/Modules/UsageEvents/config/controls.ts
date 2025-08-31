import { UsageEvent } from "@/types";
import { ListController, useControls } from "quasar-ui-danx";
import { routes } from "./routes";

export const controls = useControls("usage-events", {
    label: "Usage Events",
    routes
}) as ListController<UsageEvent>;