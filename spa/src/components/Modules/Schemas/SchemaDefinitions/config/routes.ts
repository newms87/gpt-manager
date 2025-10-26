import { apiUrls } from "@/api";
import { SchemaDefinition, SchemaDefinitionRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";

export const routes = useActionRoutes(apiUrls.schemas.definitions, {
	history(target: SchemaDefinition) {
		return request.get(`${apiUrls.schemas.definitions}/${target.id}/history`);
	}
}) as SchemaDefinitionRoutes;
