import { useActionRoutes } from "quasar-ui-danx";

const baseUrl = import.meta.env.VITE_API_URL + "/schemas/fragments";

export const routes = useActionRoutes(baseUrl);
