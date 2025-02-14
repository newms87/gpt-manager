import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const routes = useActionRoutes(API_URL + "/task-workflows");
