import { apiUrls } from "@/api";
import { useActionRoutes } from "quasar-ui-danx";

export const jobDispatchRoutes = useActionRoutes(apiUrls.audits.jobDispatches);
