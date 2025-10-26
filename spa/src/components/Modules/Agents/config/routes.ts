import { apiUrls } from "@/api";
import { useActionRoutes } from "quasar-ui-danx";

export const routes = useActionRoutes(apiUrls.agents.base);
