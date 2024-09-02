import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const PromptDirectiveRoutes = useActionRoutes(API_URL + "/prompt/directives");
