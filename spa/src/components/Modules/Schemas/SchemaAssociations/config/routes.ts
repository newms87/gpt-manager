import { useActionRoutes } from "quasar-ui-danx";

const baseUrl = import.meta.env.VITE_API_URL + "/schemas/associations";

export const routes = useActionRoutes(baseUrl);
