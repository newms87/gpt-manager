import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL + "/task-artifact-filters";

export const routes = useActionRoutes(API_URL);
