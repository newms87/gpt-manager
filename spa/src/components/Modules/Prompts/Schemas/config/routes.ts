import { PromptSchema, PromptSchemaRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";


const baseUrl = import.meta.env.VITE_API_URL + "/prompt/schemas";

export const routes = useActionRoutes(baseUrl, {
	history(target: PromptSchema) {
		return request.get(`${baseUrl}/${target.id}/history`);
	}
}) as PromptSchemaRoutes;
