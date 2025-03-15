import { useActionRoutes } from "quasar-ui-danx";

const baseUrl = import.meta.env.VITE_API_URL + "/workflow-inputs";

export const routes = useActionRoutes(baseUrl);
