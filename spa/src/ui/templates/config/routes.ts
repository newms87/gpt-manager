import { apiUrls } from "@/api";
import { useActionRoutes } from "quasar-ui-danx";

// Use the new unified templates endpoint
export const routes = useActionRoutes(apiUrls.templates.base);