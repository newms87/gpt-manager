import { describe, it, expect } from "vitest";
import { buildFragmentGraph } from "../useFragmentSelectorGraph";
import { ArtifactCategoryDefinition, FragmentSelector, JsonSchema } from "@/types";
import { simpleSchema, nestedSchema, deepSchema } from "./fixtures/testSchemas";

/**
 * Helper to find the deepest path in a fragment selector tree.
 * Exported for testing from useFragmentSelectorGraph.ts
 */
function resolveAcdTargetPath(acd: ArtifactCategoryDefinition): string {
	if (!acd.fragment_selector?.children) {
		return "root";
	}

	const childKeys = Object.keys(acd.fragment_selector.children);
	if (childKeys.length === 0) {
		return "root";
	}

	return findDeepestPath(acd.fragment_selector, "root");
}

function findDeepestPath(selector: FragmentSelector, currentPath: string): string {
	if (!selector.children) {
		return currentPath;
	}

	const keys = Object.keys(selector.children);
	if (keys.length === 0) {
		return currentPath;
	}

	const firstKey = keys[0];
	const childSelector = selector.children[firstKey];
	const childPath = currentPath === "root" ? `root.${firstKey}` : `${currentPath}.${firstKey}`;

	return findDeepestPath(childSelector, childPath);
}

/**
 * Test ACD fixtures for use across tests.
 */
const createTestAcd = (
	overrides: Partial<ArtifactCategoryDefinition> = {}
): ArtifactCategoryDefinition => ({
	id: "acd-1",
	schema_definition_id: "schema-1",
	name: "summary",
	label: "Patient Summary",
	prompt: "Generate a summary of the patient data including key demographics and conditions.",
	fragment_selector: null,
	editable: true,
	deletable: true,
	__type: "ArtifactCategoryDefinition",
	...overrides
});

describe("useFragmentSelectorGraph", () => {
	// =========================================================================
	// buildFragmentGraph without ACDs
	// =========================================================================
	describe("buildFragmentGraph without ACDs", () => {
		it("should build graph from simple schema", () => {
			const { nodes, edges } = buildFragmentGraph(simpleSchema);

			// Simple schema has only root node (no nested models)
			expect(nodes).toHaveLength(1);
			expect(nodes[0].id).toBe("root");
			expect(nodes[0].type).toBe("fragment-model");
			expect(nodes[0].data.name).toBe("Person");
			expect(nodes[0].data.path).toBe("root");

			// No edges since there are no child models
			expect(edges).toHaveLength(0);
		});

		it("should build nodes and edges for nested schema", () => {
			const { nodes, edges } = buildFragmentGraph(nestedSchema);

			// Nested schema has 2 nodes: root and patient
			expect(nodes).toHaveLength(2);

			const rootNode = nodes.find(n => n.id === "root");
			const patientNode = nodes.find(n => n.id === "root.patient");

			expect(rootNode).toBeDefined();
			expect(rootNode?.data.name).toBe("MedicalRecord");

			expect(patientNode).toBeDefined();
			expect(patientNode?.data.name).toBe("Patient");
			expect(patientNode?.data.path).toBe("root.patient");

			// One edge connecting root to patient
			expect(edges).toHaveLength(1);
			expect(edges[0].source).toBe("root");
			expect(edges[0].target).toBe("root.patient");
		});

		it("should build nodes and edges for deep schema", () => {
			const { nodes, edges } = buildFragmentGraph(deepSchema);

			// Deep schema has 4 nodes: root, hospitals, departments, staff
			expect(nodes).toHaveLength(4);

			const nodePaths = nodes.map(n => n.id);
			expect(nodePaths).toContain("root");
			expect(nodePaths).toContain("root.hospitals");
			expect(nodePaths).toContain("root.hospitals.departments");
			expect(nodePaths).toContain("root.hospitals.departments.staff");

			// Three edges connecting the hierarchy
			expect(edges).toHaveLength(3);
		});

		it("should set correct node types", () => {
			const { nodes } = buildFragmentGraph(nestedSchema);

			for (const node of nodes) {
				expect(node.type).toBe("fragment-model");
			}
		});

		it("should include properties in node data", () => {
			const { nodes } = buildFragmentGraph(nestedSchema);

			const rootNode = nodes.find(n => n.id === "root");
			expect(rootNode?.data.properties).toBeDefined();
			expect(rootNode?.data.properties.length).toBe(2); // recordId and patient

			const recordIdProp = rootNode?.data.properties.find(p => p.name === "recordId");
			expect(recordIdProp?.type).toBe("string");
			expect(recordIdProp?.isModel).toBe(false);

			const patientProp = rootNode?.data.properties.find(p => p.name === "patient");
			expect(patientProp?.type).toBe("object");
			expect(patientProp?.isModel).toBe(true);
		});
	});

	// =========================================================================
	// buildFragmentGraph with ACDs
	// =========================================================================
	describe("buildFragmentGraph with ACDs", () => {
		it("should not include ACD nodes when artifactsVisible is false", () => {
			const acd = createTestAcd();
			const { nodes, edges } = buildFragmentGraph(simpleSchema, {
				artifactCategoryDefinitions: [acd],
				artifactsVisible: false
			});

			// Should only have the root node, no ACD nodes
			expect(nodes).toHaveLength(1);
			expect(nodes[0].type).toBe("fragment-model");
			expect(edges).toHaveLength(0);
		});

		it("should not include ACD nodes when artifactsVisible is undefined", () => {
			const acd = createTestAcd();
			const { nodes, edges } = buildFragmentGraph(simpleSchema, {
				artifactCategoryDefinitions: [acd]
			});

			// Should only have the root node, no ACD nodes
			expect(nodes).toHaveLength(1);
			expect(nodes[0].type).toBe("fragment-model");
			expect(edges).toHaveLength(0);
		});

		it("should include ACD nodes when artifactsVisible is true", () => {
			const acd = createTestAcd();
			const { nodes, edges } = buildFragmentGraph(simpleSchema, {
				artifactCategoryDefinitions: [acd],
				artifactsVisible: true
			});

			// Should have root node + ACD node
			expect(nodes).toHaveLength(2);

			const acdNode = nodes.find(n => n.type === "artifact-category");
			expect(acdNode).toBeDefined();
			expect(acdNode?.id).toBe(`acd-${acd.id}`);
			expect(acdNode?.data.acd).toBe(acd);

			// Should have edge from root to ACD
			expect(edges).toHaveLength(1);
			expect(edges[0].source).toBe("root");
			expect(edges[0].target).toBe(`acd-${acd.id}`);
		});

		it("should create edges from target model to ACD node", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {
						patient: { type: "object" }
					}
				}
			});

			const { nodes, edges } = buildFragmentGraph(nestedSchema, {
				artifactCategoryDefinitions: [acd],
				artifactsVisible: true
			});

			// Find the ACD-specific edge
			const acdEdge = edges.find(e => e.id.startsWith("edge-acd-"));
			expect(acdEdge).toBeDefined();
			expect(acdEdge?.source).toBe("root.patient");
			expect(acdEdge?.target).toBe(`acd-${acd.id}`);
		});

		it("should handle multiple ACDs on same target model", () => {
			const acd1 = createTestAcd({ id: "acd-1", name: "summary" });
			const acd2 = createTestAcd({ id: "acd-2", name: "report" });

			const { nodes, edges } = buildFragmentGraph(simpleSchema, {
				artifactCategoryDefinitions: [acd1, acd2],
				artifactsVisible: true
			});

			// Should have root + 2 ACD nodes
			expect(nodes).toHaveLength(3);

			const acdNodes = nodes.filter(n => n.type === "artifact-category");
			expect(acdNodes).toHaveLength(2);

			// Both should target root
			const acdEdges = edges.filter(e => e.id.startsWith("edge-acd-"));
			expect(acdEdges).toHaveLength(2);
			expect(acdEdges[0].source).toBe("root");
			expect(acdEdges[1].source).toBe("root");
		});

		it("should handle ACDs with null fragment_selector (targets root)", () => {
			const acd = createTestAcd({ fragment_selector: null });

			const { nodes, edges } = buildFragmentGraph(nestedSchema, {
				artifactCategoryDefinitions: [acd],
				artifactsVisible: true
			});

			const acdEdge = edges.find(e => e.id.startsWith("edge-acd-"));
			expect(acdEdge?.source).toBe("root");
		});

		it("should include parentModelPath in ACD node data", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {
						patient: { type: "object" }
					}
				}
			});

			const { nodes } = buildFragmentGraph(nestedSchema, {
				artifactCategoryDefinitions: [acd],
				artifactsVisible: true
			});

			const acdNode = nodes.find(n => n.type === "artifact-category");
			expect(acdNode?.data.parentModelPath).toBe("root.patient");
		});

		it("should not include ACDs when array is empty", () => {
			const { nodes } = buildFragmentGraph(simpleSchema, {
				artifactCategoryDefinitions: [],
				artifactsVisible: true
			});

			expect(nodes).toHaveLength(1);
			expect(nodes[0].type).toBe("fragment-model");
		});
	});

	// =========================================================================
	// resolveAcdTargetPath
	// =========================================================================
	describe("resolveAcdTargetPath", () => {
		it("should return 'root' for null fragment_selector", () => {
			const acd = createTestAcd({ fragment_selector: null });
			expect(resolveAcdTargetPath(acd)).toBe("root");
		});

		it("should return 'root' for empty children", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {}
				}
			});
			expect(resolveAcdTargetPath(acd)).toBe("root");
		});

		it("should return 'root' for fragment_selector without children property", () => {
			const acd = createTestAcd({
				fragment_selector: { type: "object" }
			});
			expect(resolveAcdTargetPath(acd)).toBe("root");
		});

		it("should return direct child path for single level", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {
						patient: { type: "object" }
					}
				}
			});
			expect(resolveAcdTargetPath(acd)).toBe("root.patient");
		});

		it("should return deepest path for nested fragment_selector", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {
						hospitals: {
							type: "array",
							children: {
								departments: {
									type: "array",
									children: {
										staff: { type: "array" }
									}
								}
							}
						}
					}
				}
			});
			expect(resolveAcdTargetPath(acd)).toBe("root.hospitals.departments.staff");
		});

		it("should handle two-level nesting", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {
						patient: {
							type: "object",
							children: {
								address: { type: "object" }
							}
						}
					}
				}
			});
			expect(resolveAcdTargetPath(acd)).toBe("root.patient.address");
		});

		it("should use first key when multiple children exist at same level", () => {
			const acd = createTestAcd({
				fragment_selector: {
					type: "object",
					children: {
						alpha: { type: "object" },
						beta: { type: "object" }
					}
				}
			});
			// Should use the first key encountered
			const path = resolveAcdTargetPath(acd);
			expect(path === "root.alpha" || path === "root.beta").toBe(true);
		});
	});

	// =========================================================================
	// Edge styling for ACD nodes
	// =========================================================================
	describe("ACD edge styling", () => {
		it("should use different stroke color for ACD edges", () => {
			const acd = createTestAcd();

			const { edges } = buildFragmentGraph(simpleSchema, {
				artifactCategoryDefinitions: [acd],
				artifactsVisible: true
			});

			const acdEdge = edges.find(e => e.id.startsWith("edge-acd-"));
			expect(acdEdge?.style?.stroke).toBe("#7c3aed"); // ACD_EDGE_STROKE_COLOR
		});

		it("should use standard stroke color for model edges", () => {
			const { edges } = buildFragmentGraph(nestedSchema);

			const modelEdge = edges.find(e => !e.id.startsWith("edge-acd-"));
			expect(modelEdge?.style?.stroke).toBe("#64748b"); // EDGE_STROKE_COLOR
		});
	});
});
