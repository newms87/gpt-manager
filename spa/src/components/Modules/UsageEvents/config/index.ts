import { UsageEvent } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { columns } from "./columns";
import { controls } from "./controls";
import { routes } from "./routes";

export const dxUsageEvent = {
    ...controls,
    ...actionControls,
    menuActions,
    batchActions,
    columns,
    routes
} as DanxController<UsageEvent>;
