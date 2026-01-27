import { ArtifactCategoryDefinition, FragmentSelector, JsonSchema } from "@/types";
import { Edge, Node } from "@vue-flow/core";
import { ACD_EDGE_STROKE_COLOR, EDGE_BORDER_RADIUS, EDGE_STROKE_COLOR, EDGE_STROKE_WIDTH } from "./constants";
import { ArtifactCategoryNodeData, FragmentModelNodeData, PropertyInfo } from "./types";
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
 * Options for building the fragment graph with optional ACD nodes.
 */
export interface BuildGraphOptions {
	/** Artifact Category Definitions to display as child nodes */
	artifactCategoryDefinitions?: ArtifactCategoryDefinition[];
	/** Whether to show ACD nodes (default: false) */
	artifactsVisible?: boolean;
}

/**
 * Convert a JsonSchema tree into VueFlow nodes and edges for display
 * as a UML-style model graph with tree layout.
 *
 * Optionally includes Artifact Category Definition nodes attached to their
 * target models based on each ACD's fragment_selector.
 */
export function buildFragmentGraph(
	schema: JsonSchema,
	options?: BuildGraphOptions
): { nodes: Node[]; edges: Edge[] } {
	const nodes: Node[] = [];
	const edges: Edge[] = [];

	buildNodesRecursive(schema, "root", schema.title || "Root", nodes, edges);

	// Add ACD nodes if visible and provided
	if (options?.artifactsVisible && options?.artifactCategoryDefinitions?.length) {
		buildAcdNodes(options.artifactCategoryDefinitions, nodes, edges);
	}

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

/**
 * Build ACD nodes and edges connecting them to their target model nodes.
 * Each ACD is attached to the model it targets based on its fragment_selector.
 */
function buildAcdNodes(
	acds: ArtifactCategoryDefinition[],
	nodes: Node[],
	edges: Edge[]
): void {
	for (const acd of acds) {
		const targetPath = resolveAcdTargetPath(acd);
		const acdNodeId = `acd-${acd.id}`;

		// Create the ACD node
		const node: Node<ArtifactCategoryNodeData> = {
			id: acdNodeId,
			type: "artifact-category",
			position: { x: 0, y: 0 }, // Layout will position this
			data: {
				acd,
				direction: "LR", // Will be updated by layout
				parentModelPath: targetPath
			}
		};

		nodes.push(node);

		// Create edge from target model to ACD
		// Uses source-artifact handle on model, target-left handle on ACD
		// Using bezier for smooth curves without stepped detours
		edges.push({
			id: `edge-acd-${acd.id}`,
			source: targetPath,
			target: acdNodeId,
			sourceHandle: "source-artifact",
			targetHandle: "target-left",
			type: "bezier",
			style: { stroke: ACD_EDGE_STROKE_COLOR, strokeWidth: EDGE_STROKE_WIDTH }
		});
	}
}

/**
 * Resolve the target model path for an ACD based on its fragment_selector.
 * Returns the deepest selected model path, or "root" if no selector or empty.
 */
function resolveAcdTargetPath(acd: ArtifactCategoryDefinition): string {
	console.log("[ACD DEBUG] resolveAcdTargetPath for", acd.name);

	if (!acd.fragment_selector?.children) {
		console.log("[ACD DEBUG] No children, returning root");
		return "root";
	}

	const childKeys = Object.keys(acd.fragment_selector.children);
	if (childKeys.length === 0) {
		console.log("[ACD DEBUG] Empty children, returning root");
		return "root";
	}

	// Find the deepest model path by traversing the selector tree
	const result = findDeepestModelPath(acd.fragment_selector, "root");
	console.log("[ACD DEBUG] Resolved path:", result);
	return result;
}

/**
 * Recursively find the deepest model path in a fragment selector tree.
 * Only traverses into children that are model types (object/array), stopping at scalar properties.
 */
function findDeepestModelPath(selector: FragmentSelector, currentPath: string): string {
	if (!selector.children) {
		return currentPath;
	}

	const keys = Object.keys(selector.children);
	if (keys.length === 0) {
		return currentPath;
	}

	// Find the first child that is a model type (object or array)
	for (const key of keys) {
		const childSelector = selector.children[key];
		const childType = childSelector.type;

		// Only recurse into model types, not scalar properties
		if (childType === "object" || childType === "array") {
			const childPath = currentPath === "root" ? `root.${key}` : `${currentPath}.${key}`;
			return findDeepestModelPath(childSelector, childPath);
		}
	}

	// No model children found - current path is the deepest model
	return currentPath;
}
