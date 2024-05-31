import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const ContentSourceRoutes = useActionRoutes(API_URL + "/content-sources");
