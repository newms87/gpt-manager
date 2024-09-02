import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const AgentRoutes = useActionRoutes(API_URL + "/agents");
export const ThreadRoutes = useActionRoutes(API_URL + "/threads");
export const MessageRoutes = useActionRoutes(API_URL + "/messages");
