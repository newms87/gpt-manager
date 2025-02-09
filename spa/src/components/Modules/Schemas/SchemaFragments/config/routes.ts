import { useActionRoutes } from "quasar-ui-danx";

const baseUrl = import.meta.env.VITE_API_URL + "/prompt/schema-fragments";

export const routes = useActionRoutes(baseUrl);
