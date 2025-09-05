import { Artifact } from "@/types";
import { DanxController } from "quasar-ui-danx";
import { actionControls, batchActions, menuActions } from "./actions";
import { routes } from "./routes";

export const dxArtifact = {
    ...actionControls,
    menuActions,
    batchActions,
    routes
} as DanxController<Artifact>;
