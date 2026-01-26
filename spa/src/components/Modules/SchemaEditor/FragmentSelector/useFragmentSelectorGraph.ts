import { JsonSchema } from "@/types";
import { Edge, Node } from "@vue-flow/core";
import { EDGE_BORDER_RADIUS, EDGE_STROKE_COLOR, EDGE_STROKE_WIDTH } from "./constants";
import { FragmentModelNodeData, PropertyInfo } from "./types";
import { getSchemaProperties, isModelType } from "./useSchemaNavigation";

/**
 * Extract the properties from a schema as PropertyInfo[], handling both
 * object properties and array items properties.
 */
export function getModelProperties(schema: JsonSchema): PropertyInfo[] {
	const properties = getSchemaProperties(schema);

	if (!properties) {
		return [];
	}

	return Object.entries(properties).map(([name, childSchema]) => ({
		name,
		type: childSchema.type,
		format: childSchema.format,
		title: childSchema.items?.title || childSchema.title,
		description: childSchema.items?.description || childSchema.description,
		position: childSchema.position,
		isModel: isModelType(childSchema.type)
	}));
}

/**
 * Convert a JsonSchema tree into VueFlow nodes and edges for display
 * as a UML-style model graph with tree layout.
 */
export function buildFragmentGraph(schema: JsonSchema): { nodes: Node[]; edges: Edge[] } {
	const nodes: Node[] = [];
	const edges: Edge[] = [];

	buildNodesRecursive(schema, "root", schema.title || "Root", nodes, edges);

	return { nodes, edges };
}

/**
 * Recursively walk the schema tree, creating nodes for each object/array
 * and edges connecting parents to children.
 */
function buildNodesRecursive(
	schema: JsonSchema,
	path: string,
	name: string,
	nodes: Node[],
	edges: Edge[]
): void {
	const properties = getModelProperties(schema);

	const node: Node<FragmentModelNodeData> = {
		id: path,
		type: "fragment-model",
		position: { x: 0, y: 0 },
		data: {
			name,
			path,
			schema,
			properties,
			selectedProperties: [],
			direction: "LR"
		}
	};

	nodes.push(node);

	// Recurse into child model properties (objects and arrays)
	const childSchemaProperties = getSchemaProperties(schema);
	if (!childSchemaProperties) return;

	const modelChildren = properties.filter(p => p.isModel);

	for (const child of modelChildren) {
		const childSchema = childSchemaProperties[child.name];
		if (!childSchema) continue;

		const childPath = `${path}.${child.name}`;

		edges.push({
			id: `edge-${path}-${child.name}`,
			source: path,
			target: childPath,
			type: "smoothstep",
			style: { stroke: EDGE_STROKE_COLOR, strokeWidth: EDGE_STROKE_WIDTH },
			pathOptions: { borderRadius: EDGE_BORDER_RADIUS }
		});

		buildNodesRecursive(childSchema, childPath, child.title || child.name, nodes, edges);
	}
}
