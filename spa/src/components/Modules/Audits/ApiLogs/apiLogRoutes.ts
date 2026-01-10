import { apiUrls } from "@/api";
import { useActionRoutes } from "quasar-ui-danx";

export const apiLogRoutes = useActionRoutes(apiUrls.audits.apiLogs);
