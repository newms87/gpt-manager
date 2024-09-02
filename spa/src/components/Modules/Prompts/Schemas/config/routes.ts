import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const PromptSchemaRoutes = useActionRoutes(API_URL + "/prompt/schemas");
