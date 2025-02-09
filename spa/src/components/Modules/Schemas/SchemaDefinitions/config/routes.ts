import { SchemaDefinition, SchemaDefinitionRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";


const baseUrl = import.meta.env.VITE_API_URL + "/schemas/definitions";

export const routes = useActionRoutes(baseUrl, {
	history(target: SchemaDefinition) {
		return request.get(`${baseUrl}/${target.id}/history`);
	}
}) as SchemaDefinitionRoutes;
