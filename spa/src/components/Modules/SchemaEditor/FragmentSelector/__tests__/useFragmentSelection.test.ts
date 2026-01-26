import { describe, it, expect } from "vitest";
import { ref } from "vue";
import { useFragmentSelection } from "../useFragmentSelection";
import { getModelProperties, SelectionMode } from "../useFragmentSelectorGraph";
import {
	simpleSchema,
	nestedSchema,
	arraySchema,
	deepSchema,
	emptySchema,
	modelOnlySchema,
	multipleSiblingModelsSchema
} from "./fixtures/testSchemas";

describe("useFragmentSelection", () => {
	// =========================================================================
	// 4.1 Selection Map State Tests
	// =========================================================================
	describe("Selection Map State", () => {
		it("should have empty initial state", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap } = useFragmentSelection(schema, mode, recursive);

			expect(selectionMap.size).toBe(0);
		});

		it("should add property to selection when toggled", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "name" });

			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("name")).toBe(true);
		});

		it("should remove property from selection when toggled again", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "name" });
			onToggleProperty({ path: "root", propertyName: "name" });

			expect(selectionMap.has("root")).toBe(false);
		});

		it("should maintain parent chain when selecting nested property", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// First select the patient model property
			onToggleProperty({ path: "root", propertyName: "patient" });
			// Then select a property within patient
			onToggleProperty({ path: "root.patient", propertyName: "name" });

			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			expect(selectionMap.has("root.patient")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("name")).toBe(true);
		});
	});

	// =========================================================================
	// 4.2 By-Property Non-Recursive Mode Tests (formerly single-node)
	// =========================================================================
	describe("By-Property Non-Recursive Mode", () => {
		it("should select only current node scalar properties with toggle all", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			expect(selectionMap.has("root")).toBe(true);
			// Scalar properties should be selected
			expect(selectionMap.get("root")!.has("recordId")).toBe(true);
			// Model properties should NOT be selected in non-recursive mode
			expect(selectionMap.get("root")!.has("patient")).toBe(false);
			// Child node should not be selected
			expect(selectionMap.has("root.patient")).toBe(false);
		});

		it("should deselect only current node with toggle all false", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// First select root and child manually
			onToggleAll({ path: "root", selectAll: true });
			onToggleProperty({ path: "root.patient", propertyName: "name" });

			// Now deselect root only
			onToggleAll({ path: "root", selectAll: false });

			expect(selectionMap.has("root")).toBe(false);
			// Child selection should remain
			expect(selectionMap.has("root.patient")).toBe(true);
		});

		it("should ensure parent chain when selecting nested node", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root.patient", selectAll: true });

			// Parent chain should be selected
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			// Current node should have all its properties
			expect(selectionMap.has("root.patient")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("name")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("dob")).toBe(true);
		});
	});

	// =========================================================================
	// 4.3 By-Property Recursive Mode Tests
	// =========================================================================
	describe("By-Property Recursive Mode", () => {
		it("should select all properties recursively with toggle all", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("recordId")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			expect(selectionMap.has("root.patient")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("name")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("dob")).toBe(true);
		});

		it("should deselect all properties recursively with toggle all false", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });
			onToggleAll({ path: "root", selectAll: false });

			expect(selectionMap.size).toBe(0);
		});

		it("should handle deeply nested structures", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			// Check all levels are selected
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.has("root.hospitals")).toBe(true);
			expect(selectionMap.has("root.hospitals.departments")).toBe(true);
			expect(selectionMap.has("root.hospitals.departments.staff")).toBe(true);

			// Verify specific properties at each level
			expect(selectionMap.get("root")!.has("systemId")).toBe(true);
			expect(selectionMap.get("root.hospitals")!.has("name")).toBe(true);
			expect(selectionMap.get("root.hospitals.departments")!.has("floor")).toBe(true);
			expect(selectionMap.get("root.hospitals.departments.staff")!.has("employeeId")).toBe(true);
		});
	});

	// =========================================================================
	// 4.4 By-Model Non-Recursive Mode Tests (formerly model-only)
	// =========================================================================
	describe("By-Model Non-Recursive Mode", () => {
		it("should include model without properties when toggling", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root.patient", selectAll: true });

			// Parent chain should exist
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			// But no selection set at child path (by-model doesn't select properties)
			expect(selectionMap.has("root.patient")).toBe(false);
		});

		it("should exclude model when deselecting", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root.patient", selectAll: true });
			onToggleAll({ path: "root.patient", selectAll: false });

			// Root remains in the map (special case) but patient should be removed from it
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(false);
			expect(selectionMap.get("root")!.size).toBe(0);
		});

		it("should handle root special case", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			// Root should be in map with empty set
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.size).toBe(0);

			// Deselecting root should clear everything
			onToggleAll({ path: "root", selectAll: false });
			expect(selectionMap.size).toBe(0);
		});
	});

	// =========================================================================
	// 4.4b By-Model Recursive Mode Tests (NEW!)
	// =========================================================================
	describe("By-Model Recursive Mode", () => {
		it("should include all models recursively without properties", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			// Root should have model children in its selection set
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			// Leaf model (patient has no model children) should have empty set
			expect(selectionMap.has("root.patient")).toBe(true);
			expect(selectionMap.get("root.patient")!.size).toBe(0);

			// Fragment should show nested structure
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					patient: { type: "object" }
				}
			});
		});

		it("should handle deeply nested models recursively", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			// All model nodes should be in map
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.has("root.hospitals")).toBe(true);
			expect(selectionMap.has("root.hospitals.departments")).toBe(true);
			expect(selectionMap.has("root.hospitals.departments.staff")).toBe(true);

			// Parent nodes should have their model children in their selection sets
			expect(selectionMap.get("root")!.has("hospitals")).toBe(true);
			expect(selectionMap.get("root.hospitals")!.has("departments")).toBe(true);
			expect(selectionMap.get("root.hospitals.departments")!.has("staff")).toBe(true);
			// Only leaf model (staff) should have empty set
			expect(selectionMap.get("root.hospitals.departments.staff")!.size).toBe(0);

			// Fragment should show full nested structure
			expect(fragmentSelector.value).toEqual({
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
			});
		});

		it("should deselect all models recursively", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });
			onToggleAll({ path: "root", selectAll: false });

			expect(selectionMap.size).toBe(0);
		});

		it("should select only subtree when selecting nested model", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root.hospitals.departments", selectAll: true });

			// Parent chain should be established
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("hospitals")).toBe(true);
			expect(selectionMap.has("root.hospitals")).toBe(true);
			expect(selectionMap.get("root.hospitals")!.has("departments")).toBe(true);

			// Selected node should have its model children
			expect(selectionMap.has("root.hospitals.departments")).toBe(true);
			expect(selectionMap.get("root.hospitals.departments")!.has("staff")).toBe(true);
			// Leaf model (staff) should have empty set
			expect(selectionMap.has("root.hospitals.departments.staff")).toBe(true);
			expect(selectionMap.get("root.hospitals.departments.staff")!.size).toBe(0);

			// Fragment should show the nested structure
			expect(fragmentSelector.value).toEqual({
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
			});
		});
	});

	// =========================================================================
	// 4.5 hasAnySelection Tests
	// =========================================================================
	describe("hasAnySelection", () => {
		it("should return false for empty selection", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState } = useFragmentSelection(schema, mode, recursive);

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.hasAnySelection).toBe(false);
		});

		it("should return true for direct selection", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "name" });

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.hasAnySelection).toBe(true);
		});

		it("should return true for descendant selection", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select a property in child node
			onToggleProperty({ path: "root", propertyName: "patient" });
			onToggleProperty({ path: "root.patient", propertyName: "name" });

			const properties = getModelProperties(nestedSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.hasAnySelection).toBe(true);
		});

		it("should return false for unrelated paths", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select only a root-level scalar property
			onToggleProperty({ path: "root", propertyName: "recordId" });

			// Get properties of the patient sub-schema
			const patientSchema = nestedSchema.properties!.patient;
			const patientProperties = getModelProperties(patientSchema);
			const state = getSelectionRollupState("root.patient", patientProperties);

			expect(state.hasAnySelection).toBe(false);
		});
	});

	// =========================================================================
	// 4.6 isFullySelected Tests
	// =========================================================================
	describe("isFullySelected", () => {
		it("should return false for empty selection", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState } = useFragmentSelection(schema, mode, recursive);

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.isFullySelected).toBe(false);
		});

		it("should return true when all scalar properties selected", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select all scalar properties
			onToggleProperty({ path: "root", propertyName: "name" });
			onToggleProperty({ path: "root", propertyName: "age" });
			onToggleProperty({ path: "root", propertyName: "active" });

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.isFullySelected).toBe(true);
		});

		it("should return false when some properties missing", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select only some properties
			onToggleProperty({ path: "root", propertyName: "name" });
			onToggleProperty({ path: "root", propertyName: "age" });
			// 'active' is missing

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.isFullySelected).toBe(false);
		});

		it("should consider model children for full selection", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select root properties but not child model properties
			onToggleProperty({ path: "root", propertyName: "recordId" });
			onToggleProperty({ path: "root", propertyName: "patient" });

			const properties = getModelProperties(nestedSchema);
			const state = getSelectionRollupState("root", properties);

			// Not fully selected because patient's children are not selected
			expect(state.isFullySelected).toBe(false);
		});

		it("should return true only when entire subtree fully selected", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Use recursive toggle to select everything
			onToggleAll({ path: "root", selectAll: true });

			const properties = getModelProperties(nestedSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.isFullySelected).toBe(true);
		});
	});

	// =========================================================================
	// 4.7 getSelectionRollupState Tests
	// =========================================================================
	describe("getSelectionRollupState", () => {
		it("should combine hasAnySelection and isFullySelected", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Partial selection
			onToggleProperty({ path: "root", propertyName: "name" });

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties);

			expect(state.hasAnySelection).toBe(true);
			expect(state.isFullySelected).toBe(false);
		});

		it("should derive ternary state correctly", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			const properties = getModelProperties(simpleSchema);

			// No selection -> (false, false)
			let state = getSelectionRollupState("root", properties);
			expect(state.hasAnySelection).toBe(false);
			expect(state.isFullySelected).toBe(false);

			// Full selection -> (true, true)
			onToggleAll({ path: "root", selectAll: true });
			state = getSelectionRollupState("root", properties);
			expect(state.hasAnySelection).toBe(true);
			expect(state.isFullySelected).toBe(true);
		});
	});

	// =========================================================================
	// 4.8 FragmentSelector Output Tests
	// =========================================================================
	describe("FragmentSelector Output", () => {
		it("should return empty object when no selection", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			expect(fragmentSelector.value).toEqual({});
		});

		it("should build simple flat selector", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "name" });
			onToggleProperty({ path: "root", propertyName: "age" });

			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					name: { type: "string" },
					age: { type: "number" }
				}
			});
		});

		it("should build nested selector", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "patient" });
			onToggleProperty({ path: "root.patient", propertyName: "name" });

			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					patient: {
						type: "object",
						children: {
							name: { type: "string" }
						}
					}
				}
			});
		});

		it("should handle mixed scalar and model properties", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "recordId" });
			onToggleProperty({ path: "root", propertyName: "patient" });
			onToggleProperty({ path: "root.patient", propertyName: "dob" });

			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					recordId: { type: "string" },
					patient: {
						type: "object",
						children: {
							dob: { type: "string" }
						}
					}
				}
			});
		});

		it("should handle array items structure", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "providers" });
			onToggleProperty({ path: "root.providers", propertyName: "name" });
			onToggleProperty({ path: "root.providers", propertyName: "npi" });

			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					providers: {
						type: "array",
						children: {
							name: { type: "string" },
							npi: { type: "string" }
						}
					}
				}
			});
		});
	});

	// =========================================================================
	// 4.9 External Sync Tests
	// =========================================================================
	describe("External Sync", () => {
		it("should clear existing state when syncing", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty, syncFromExternal } = useFragmentSelection(schema, mode, recursive);

			// Add some existing selection
			onToggleProperty({ path: "root", propertyName: "name" });
			expect(selectionMap.size).toBe(1);

			// Sync with null should clear
			syncFromExternal(null);
			expect(selectionMap.size).toBe(0);
		});

		it("should parse selector into selectionMap", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, syncFromExternal } = useFragmentSelection(schema, mode, recursive);

			const externalSelector = {
				type: "object" as const,
				children: {
					recordId: { type: "string" as const },
					patient: {
						type: "object" as const,
						children: {
							name: { type: "string" as const }
						}
					}
				}
			};

			syncFromExternal(externalSelector);

			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("recordId")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			expect(selectionMap.has("root.patient")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("name")).toBe(true);
		});

		it("should handle null input", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty, syncFromExternal } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "name" });
			syncFromExternal(null);

			expect(selectionMap.size).toBe(0);
		});

		it("should maintain round-trip integrity", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, syncFromExternal, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Build a selection
			onToggleProperty({ path: "root", propertyName: "recordId" });
			onToggleProperty({ path: "root", propertyName: "patient" });
			onToggleProperty({ path: "root.patient", propertyName: "name" });
			onToggleProperty({ path: "root.patient", propertyName: "dob" });

			// Capture the output
			const originalSelector = JSON.parse(JSON.stringify(fragmentSelector.value));

			// Sync from the output
			syncFromExternal(originalSelector);

			// Should produce the same output
			expect(fragmentSelector.value).toEqual(originalSelector);
		});
	});

	// =========================================================================
	// 4.10 Edge Case Tests
	// =========================================================================
	describe("Edge Cases", () => {
		it("should handle empty schema (no properties)", () => {
			const schema = ref(emptySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Toggle all on empty schema shouldn't crash
			onToggleAll({ path: "root", selectAll: true });

			// Selection map should be empty (no properties to select)
			expect(selectionMap.size).toBe(0);
			expect(fragmentSelector.value).toEqual({});
		});

		it("should handle invalid path gracefully", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Toggle a property at a non-existent path
			onToggleProperty({ path: "root.nonexistent", propertyName: "foo" });

			// Should still add to selection map (even if path doesn't exist in schema)
			expect(selectionMap.has("root.nonexistent")).toBe(true);
			expect(selectionMap.get("root.nonexistent")!.has("foo")).toBe(true);
		});

		it("should cleanup selection path when all properties deselected", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			onToggleProperty({ path: "root", propertyName: "name" });
			expect(selectionMap.has("root")).toBe(true);

			onToggleProperty({ path: "root", propertyName: "name" });
			expect(selectionMap.has("root")).toBe(false);
		});

		it("should correctly resolve array vs object property types", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select scalar at root
			onToggleProperty({ path: "root", propertyName: "facilityName" });
			// Select array property
			onToggleProperty({ path: "root", propertyName: "providers" });

			const selector = fragmentSelector.value;

			expect(selector.children!.facilityName.type).toBe("string");
			expect(selector.children!.providers.type).toBe("array");
		});
	});

	// =========================================================================
	// 4.11 Mode-Aware isFullySelected Tests
	// =========================================================================
	describe("Mode-aware isFullySelected", () => {
		it("should return isFullySelected true in non-recursive mode when current node properties selected but children not", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Select all properties at root (recordId + patient model)
			onToggleAll({ path: "root", selectAll: true });

			const properties = getModelProperties(nestedSchema);
			// Pass recursive=false to getSelectionRollupState
			const state = getSelectionRollupState("root", properties, false);

			// Should be fully selected because in non-recursive mode we don't recurse into children
			expect(state.isFullySelected).toBe(true);
		});

		it("should return isFullySelected false in recursive mode when children not fully selected", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Select all properties at root only (non-recursive mode)
			onToggleAll({ path: "root", selectAll: true });

			const properties = getModelProperties(nestedSchema);
			// Now check with recursive=true - should be false because children aren't selected
			const state = getSelectionRollupState("root", properties, true);

			expect(state.isFullySelected).toBe(false);
		});

		it("should handle deep nesting in non-recursive mode", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Select all properties at root in non-recursive mode
			onToggleAll({ path: "root", selectAll: true });

			const properties = getModelProperties(deepSchema);
			const state = getSelectionRollupState("root", properties, false);

			// In non-recursive mode, only current node matters - children are ignored
			expect(state.isFullySelected).toBe(true);
			expect(state.hasAnySelection).toBe(true);
		});
	});

	// =========================================================================
	// Additional Coverage Tests
	// =========================================================================
	describe("Additional Coverage", () => {
		it("should handle model-only schema correctly", () => {
			const schema = ref(modelOnlySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			onToggleAll({ path: "root", selectAll: true });

			expect(fragmentSelector.value.type).toBe("object");
			expect(fragmentSelector.value.children).toHaveProperty("details");
			expect(fragmentSelector.value.children).toHaveProperty("metadata");
			expect(fragmentSelector.value.children!.details.children).toHaveProperty("info");
			expect(fragmentSelector.value.children!.metadata.children).toHaveProperty("version");
		});

		it("should switch selection modes correctly", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// First use recursive mode
			onToggleAll({ path: "root", selectAll: true });
			expect(selectionMap.has("root.patient")).toBe(true);

			// Clear and switch to non-recursive
			onToggleAll({ path: "root", selectAll: false });
			recursive.value = false;
			onToggleAll({ path: "root", selectAll: true });

			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.has("root.patient")).toBe(false);
		});

		it("should handle deeply nested deselection", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Select everything
			onToggleAll({ path: "root", selectAll: true });

			// Deselect only the staff level
			onToggleAll({ path: "root.hospitals.departments.staff", selectAll: false });

			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.has("root.hospitals")).toBe(true);
			expect(selectionMap.has("root.hospitals.departments")).toBe(true);
			expect(selectionMap.has("root.hospitals.departments.staff")).toBe(false);
		});

		it("should handle selector with type but no children (by-model mode)", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(false);
			const { selectionMap, syncFromExternal } = useFragmentSelection(schema, mode, recursive);

			// External selector with just type (no children)
			syncFromExternal({ type: "object" });

			// Should mark root as selected with empty set
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.size).toBe(0);
		});

		it("should build correct selector when model property selected but no child properties", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { fragmentSelector, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select only the patient model property (not its children)
			onToggleProperty({ path: "root", propertyName: "patient" });

			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					patient: { type: "object" }
				}
			});
		});
	});

	// =========================================================================
	// Bug Fix: Non-Recursive Mode Scalar-Only Selection Tests
	// =========================================================================
	describe("Non-recursive mode should only select scalar properties", () => {
		it("should only select scalar properties (not model properties) in non-recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Use onToggleAll which dispatches to onToggleSingleNode in non-recursive mode
			onToggleAll({ path: "root", selectAll: true });

			// In non-recursive mode, only scalar properties should be selected
			// nestedSchema root has: recordId (string), patient (object)
			// Only recordId (scalar) should be selected, NOT patient (model)
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("recordId")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(false);
		});

		it("should not include model properties in fragment output for non-recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { fragmentSelector, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Toggle all at root in non-recursive mode
			onToggleAll({ path: "root", selectAll: true });

			// Fragment should only contain scalar properties
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					recordId: { type: "string" }
				}
			});
			// patient (model) should NOT be in the fragment
			expect(fragmentSelector.value.children).not.toHaveProperty("patient");
		});

		it("should select only scalars when using onToggleAll in non-recursive mode", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select root in non-recursive mode
			onToggleAll({ path: "root", selectAll: true });

			// deepSchema root has: systemId (string), hospitals (array/model)
			// Only systemId should be selected
			expect(selectionMap.get("root")!.has("systemId")).toBe(true);
			expect(selectionMap.get("root")!.has("hospitals")).toBe(false);

			// Fragment should only have scalar
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					systemId: { type: "string" }
				}
			});
		});

		it("should select only scalars at nested path in non-recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// First select root.patient in non-recursive mode
			onToggleAll({ path: "root.patient", selectAll: true });

			// root.patient has: name (string), dob (string) - both scalars
			// All should be selected since they're all scalars
			expect(selectionMap.get("root.patient")!.has("name")).toBe(true);
			expect(selectionMap.get("root.patient")!.has("dob")).toBe(true);
		});
	});

	// =========================================================================
	// Bug Fix: isFullySelected UI State Alignment Tests
	// =========================================================================
	describe("isFullySelected should align with fragment logic", () => {
		it("should return true in non-recursive mode when only scalars are selected", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { getSelectionRollupState, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select only the scalar property at root (not the patient model)
			onToggleProperty({ path: "root", propertyName: "recordId" });

			const properties = getModelProperties(nestedSchema);
			const state = getSelectionRollupState("root", properties, false);

			// In non-recursive mode, selecting all SCALARS means fully selected
			// since we don't select models in non-recursive mode
			expect(state.isFullySelected).toBe(true);
		});

		it("should show checkbox as checked (not indeterminate) when fragment shows node selected", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Use non-recursive toggle at root
			onToggleAll({ path: "root", selectAll: true });

			// Get state - this drives the checkbox
			const properties = getModelProperties(nestedSchema);
			const state = getSelectionRollupState("root", properties, false);

			// Since we're in non-recursive mode and used non-recursive toggle,
			// the checkbox should show as FULLY SELECTED (not indeterminate)
			expect(state.hasAnySelection).toBe(true);
			expect(state.isFullySelected).toBe(true);
		});

		it("should correctly report fully selected state for node with only scalar children", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { getSelectionRollupState, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// simpleSchema has only scalars: name, age, active
			onToggleAll({ path: "root", selectAll: true });

			const properties = getModelProperties(simpleSchema);
			const state = getSelectionRollupState("root", properties, false);

			expect(state.isFullySelected).toBe(true);
		});
	});

	// =========================================================================
	// Bug Fix: Checkbox Shows Checked When Path Is In SelectionMap (Even Empty Set)
	// =========================================================================
	describe("Checkbox shows checked when path is in selectionMap with empty Set", () => {
		it("should show checkbox checked when path is in selectionMap with empty Set", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, getSelectionRollupState, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Step 1: Select providers (auto-selects root as parent chain)
			onToggleAll({ path: "root.providers", selectAll: true });

			// Verify root is in selectionMap
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("providers")).toBe(true);

			// Step 2: Deselect providers (root stays in selectionMap with empty Set)
			onToggleAll({ path: "root.providers", selectAll: false });

			// Verify root is still in selectionMap with empty Set (sticky parent)
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.size).toBe(0);

			// Step 3: Fragment shows root IS selected (type: "object" with no children)
			expect(fragmentSelector.value).toEqual({ type: "object" });

			// Step 4: hasAnySelection MUST return true because root IS in the fragment
			const rootProperties = getModelProperties(arraySchema);
			const rootState = getSelectionRollupState("root", rootProperties);

			// THE FIX: This MUST be true because root is in selectionMap and appears in fragment
			expect(rootState.hasAnySelection).toBe(true);
		});

		it("should show hasAnySelection true for root when in selectionMap with empty Set after by-model toggle", () => {
			const schema = ref(simpleSchema);
			const mode = ref<SelectionMode>("by-model");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, getSelectionRollupState, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// In by-model mode, toggle root to select (adds root with empty Set)
			onToggleAll({ path: "root", selectAll: true });

			// Verify root is in selectionMap with empty Set
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.size).toBe(0);

			// Fragment shows root is selected
			expect(fragmentSelector.value).toEqual({ type: "object" });

			// hasAnySelection MUST return true
			const rootProperties = getModelProperties(simpleSchema);
			const rootState = getSelectionRollupState("root", rootProperties);
			expect(rootState.hasAnySelection).toBe(true);
		});
	});

	// =========================================================================
	// Bug Fix: Checkbox Must Match Fragment State
	// =========================================================================
	describe("Checkbox must match fragment state", () => {
		it("should show model as selected when it appears in fragment even with no scalar children selected", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty, fragmentSelector, selectionMap } = useFragmentSelection(schema, mode, recursive);

			// Select providers (a model property) - this adds "providers" to root's selection
			onToggleProperty({ path: "root", propertyName: "providers" });

			// Verify fragment shows providers (even though no scalar children are selected)
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					providers: { type: "array" }
				}
			});

			// Verify selectionMap state: root has "providers", but root.providers has nothing
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("providers")).toBe(true);
			expect(selectionMap.has("root.providers")).toBe(false); // No scalar children selected

			// THE BUG: hasAnySelection should be TRUE because providers appears in fragment
			const properties = getModelProperties(arraySchema);
			const state = getSelectionRollupState("root", properties);

			// If a model appears in the fragment, the checkbox MUST show as selected (not unchecked)
			expect(state.hasAnySelection).toBe(true);
		});

		it("should show child model node checkbox as checked when model is in fragment but has no scalars selected", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty, fragmentSelector, selectionMap } = useFragmentSelection(schema, mode, recursive);

			// Select providers (a model property) - this adds "providers" to root's selection
			onToggleProperty({ path: "root", propertyName: "providers" });

			// Verify fragment shows providers
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					providers: { type: "array" }
				}
			});

			// Verify selectionMap state
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("providers")).toBe(true);
			expect(selectionMap.has("root.providers")).toBe(false); // No scalars selected within providers

			// NOW THE KEY TEST: Check the state for the PROVIDERS node itself
			// The Provider node needs to know it's "included" in the output
			const providersSchema = arraySchema.properties!.providers;
			const providersProperties = getModelProperties(providersSchema);
			const providersState = getSelectionRollupState("root.providers", providersProperties);

			// THE BUG: This currently returns false because hasAnySelection only checks
			// if selectionMap has the path "root.providers" with size > 0
			// But providers IS in the fragment (parent has it selected)
			// So the checkbox should show as CHECKED (hasAnySelection = true)
			expect(providersState.hasAnySelection).toBe(true);
		});

		it("should show model checkbox as checked when model is selected but all scalar children deselected", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty, fragmentSelector, selectionMap } = useFragmentSelection(schema, mode, recursive);

			// Select providers and its scalar children
			onToggleProperty({ path: "root", propertyName: "providers" });
			onToggleProperty({ path: "root.providers", propertyName: "name" });
			onToggleProperty({ path: "root.providers", propertyName: "npi" });

			// Verify fully selected state
			expect(selectionMap.get("root.providers")!.has("name")).toBe(true);
			expect(selectionMap.get("root.providers")!.has("npi")).toBe(true);

			// Now deselect all scalar children of providers
			onToggleProperty({ path: "root.providers", propertyName: "name" });
			onToggleProperty({ path: "root.providers", propertyName: "npi" });

			// root.providers should be cleaned up (empty set removed)
			expect(selectionMap.has("root.providers")).toBe(false);

			// But providers should STILL be in root's selection
			expect(selectionMap.get("root")!.has("providers")).toBe(true);

			// Fragment should still show providers (structure only, no children)
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					providers: { type: "array" }
				}
			});

			// For the providers node itself, check its state
			const providersSchema = arraySchema.properties!.providers;
			const providersProperties = getModelProperties(providersSchema);
			const providersState = getSelectionRollupState("root.providers", providersProperties);

			// THE BUG: The providers node checkbox should show as checked because it appears in output
			expect(providersState.hasAnySelection).toBe(true);

			// For root, it definitely has selection
			const rootProperties = getModelProperties(arraySchema);
			const rootState = getSelectionRollupState("root", rootProperties);
			expect(rootState.hasAnySelection).toBe(true);
		});

		it("should show hasAnySelection true for parent when child model is selected", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty, selectionMap } = useFragmentSelection(schema, mode, recursive);

			// Select only the patient model property (not its children)
			onToggleProperty({ path: "root", propertyName: "patient" });

			// Verify selection state
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);
			expect(selectionMap.has("root.patient")).toBe(false); // No children selected

			// Root should show hasAnySelection = true
			const rootProperties = getModelProperties(nestedSchema);
			const rootState = getSelectionRollupState("root", rootProperties);
			expect(rootState.hasAnySelection).toBe(true);
		});

		it("should show child model node checkbox as checked when parent has it selected (nested case)", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { getSelectionRollupState, onToggleProperty, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select patient model property from root
			onToggleProperty({ path: "root", propertyName: "patient" });

			// Verify fragment shows patient
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					patient: { type: "object" }
				}
			});

			// Check the patient node's state
			const patientSchema = nestedSchema.properties!.patient;
			const patientProperties = getModelProperties(patientSchema);
			const patientState = getSelectionRollupState("root.patient", patientProperties);

			// Patient is in the fragment, so its checkbox should show as checked
			expect(patientState.hasAnySelection).toBe(true);
		});
	});

	// =========================================================================
	// Bug Fix: Parent Chain Should Stay Selected When Child Is Deselected
	// =========================================================================
	describe("Parent chain should stay selected when child is deselected", () => {
		it("should keep parent selected when child is deselected", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Step 1: Fresh state
			expect(selectionMap.size).toBe(0);

			// Step 2: Select Provider (this auto-selects root as parent chain via ensureParentChainSelected)
			onToggleAll({ path: "root.providers", selectAll: true });

			// Root should now be in selectionMap (parent chain was selected)
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("providers")).toBe(true);
			// root.providers should have its scalar properties selected
			expect(selectionMap.has("root.providers")).toBe(true);

			// Step 3: Deselect Provider
			onToggleAll({ path: "root.providers", selectAll: false });

			// Step 4: EXPECTED: Root should STILL be selected
			expect(selectionMap.has("root")).toBe(true);
		});

		it("should keep parent selected with its scalar properties when child is deselected in recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select root scalar first, then select patient
			onToggleProperty({ path: "root", propertyName: "recordId" });
			onToggleAll({ path: "root.patient", selectAll: true });

			// Verify both are selected
			expect(selectionMap.get("root")!.has("recordId")).toBe(true);
			expect(selectionMap.get("root")!.has("patient")).toBe(true);

			// Deselect patient
			onToggleAll({ path: "root.patient", selectAll: false });

			// Root should STILL be selected with recordId
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")!.has("recordId")).toBe(true);
			// Patient should be removed from root's selection
			expect(selectionMap.get("root")!.has("patient")).toBe(false);
		});
	});

	// =========================================================================
	// Deselection Bug Regression Tests
	// =========================================================================
	describe("Deselection removes node from parent selection", () => {
		it("should remove node from parent selection when deselecting in recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select patient (this adds "patient" to root's selection)
			onToggleAll({ path: "root.patient", selectAll: true });

			// Verify patient is in root's selection
			expect(selectionMap.get("root")?.has("patient")).toBe(true);

			// Deselect patient
			onToggleAll({ path: "root.patient", selectAll: false });

			// Patient should be REMOVED from root's selection
			expect(selectionMap.get("root")?.has("patient") ?? false).toBe(false);

			// Root should STILL be in selectionMap (parent chain is sticky)
			expect(selectionMap.has("root")).toBe(true);
			// Fragment shows root is included but has no children
			expect(fragmentSelector.value).toEqual({ type: "object" });
		});

		it("should remove node from parent selection when deselecting in non-recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select patient
			onToggleAll({ path: "root.patient", selectAll: true });

			// Verify patient is in root's selection
			expect(selectionMap.get("root")?.has("patient")).toBe(true);

			// Deselect patient
			onToggleAll({ path: "root.patient", selectAll: false });

			// Patient should be REMOVED from root's selection
			expect(selectionMap.get("root")?.has("patient") ?? false).toBe(false);

			// Root should STILL be in selectionMap (parent chain is sticky)
			expect(selectionMap.has("root")).toBe(true);
			// Fragment shows root is included but has no children
			expect(fragmentSelector.value).toEqual({ type: "object" });
		});

		it("should keep parent in selectionMap when all children deselected (sticky parent chain)", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll } = useFragmentSelection(schema, mode, recursive);

			// Select only patient (no other root properties)
			onToggleAll({ path: "root.patient", selectAll: true });

			// Deselect patient
			onToggleAll({ path: "root.patient", selectAll: false });

			// Root should STILL be in map (parent chain is sticky)
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.size).toBe(0);
		});

		it("should remove array node from parent selection when deselecting in recursive mode", () => {
			const schema = ref(arraySchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select providers array
			onToggleAll({ path: "root.providers", selectAll: true });

			// Verify providers is in root's selection
			expect(selectionMap.get("root")?.has("providers")).toBe(true);

			// Deselect providers
			onToggleAll({ path: "root.providers", selectAll: false });

			// Providers should be REMOVED from root's selection
			expect(selectionMap.get("root")?.has("providers") ?? false).toBe(false);

			// Root should STILL be in selectionMap (parent chain is sticky)
			expect(selectionMap.has("root")).toBe(true);
			// Fragment shows root is included but has no children
			expect(fragmentSelector.value).toEqual({ type: "object" });
		});

		it("should preserve sibling selections when deselecting one child in recursive mode", () => {
			const schema = ref(nestedSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, onToggleProperty, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select both recordId and patient
			onToggleProperty({ path: "root", propertyName: "recordId" });
			onToggleAll({ path: "root.patient", selectAll: true });

			// Verify both are selected
			expect(selectionMap.get("root")?.has("recordId")).toBe(true);
			expect(selectionMap.get("root")?.has("patient")).toBe(true);

			// Deselect patient only
			onToggleAll({ path: "root.patient", selectAll: false });

			// recordId should STILL be selected
			expect(selectionMap.get("root")?.has("recordId")).toBe(true);
			// Patient should be removed
			expect(selectionMap.get("root")?.has("patient")).toBe(false);

			// Fragment should only contain recordId
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					recordId: { type: "string" }
				}
			});
		});
	});

	// =========================================================================
	// Bug Fix: Inconsistent Deselection - Parent Should Not Be Removed When Other Children Selected
	// =========================================================================
	describe("Deselection should not remove parent when other children are selected", () => {
		it("should not deselect root when deselecting Provider if Facility is still selected", () => {
			const schema = ref(multipleSiblingModelsSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Step 1: Select Provider (this adds "provider" to root's selection and selects root.provider scalars)
			onToggleAll({ path: "root.provider", selectAll: true });

			// Verify Provider is selected
			expect(selectionMap.get("root")?.has("provider")).toBe(true);
			expect(selectionMap.has("root.provider")).toBe(true);

			// Step 2: Select Facility (this adds "facility" to root's selection)
			onToggleAll({ path: "root.facility", selectAll: true });

			// Verify both are selected
			expect(selectionMap.get("root")?.has("provider")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);

			// Step 3: Deselect Provider
			onToggleAll({ path: "root.provider", selectAll: false });

			// Provider should be removed from root's selection
			expect(selectionMap.get("root")?.has("provider") ?? false).toBe(false);

			// BUT Facility should STILL be in root's selection
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);

			// Fragment should still contain facility
			expect(fragmentSelector.value.children).toHaveProperty("facility");
			expect(fragmentSelector.value.children).not.toHaveProperty("provider");
		});

		it("should not deselect parent when deselecting one sibling if other sibling has descendants selected", () => {
			const schema = ref(multipleSiblingModelsSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select both Provider and Facility fully
			onToggleAll({ path: "root.provider", selectAll: true });
			onToggleAll({ path: "root.facility", selectAll: true });

			// Verify both are fully selected
			expect(selectionMap.get("root")?.has("provider")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);
			expect(selectionMap.has("root.provider")).toBe(true);
			expect(selectionMap.has("root.facility")).toBe(true);

			// Deselect Provider only
			onToggleAll({ path: "root.provider", selectAll: false });

			// Root should STILL exist because Facility is still selected
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);
			expect(selectionMap.get("root")?.has("provider") ?? false).toBe(false);

			// Facility subtree should still be selected
			expect(selectionMap.has("root.facility")).toBe(true);

			// Fragment should only contain facility
			expect(fragmentSelector.value).toEqual({
				type: "object",
				children: {
					facility: {
						type: "array",
						children: {
							name: { type: "string" },
							address: { type: "string" }
						}
					}
				}
			});
		});

		it("should correctly handle scenario: select Provider, select Facility, deselect Provider", () => {
			const schema = ref(multipleSiblingModelsSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, getSelectionRollupState } = useFragmentSelection(schema, mode, recursive);

			// Select Provider
			onToggleAll({ path: "root.provider", selectAll: true });

			// Verify root has provider
			expect(selectionMap.get("root")?.has("provider")).toBe(true);

			// Select Facility
			onToggleAll({ path: "root.facility", selectAll: true });

			// Verify root has both
			expect(selectionMap.get("root")?.has("provider")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);

			// Deselect Provider
			onToggleAll({ path: "root.provider", selectAll: false });

			// Root MUST still exist and have facility
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);

			// Get root's selection rollup state - should show hasAnySelection = true
			const rootProperties = getModelProperties(multipleSiblingModelsSchema);
			const rootState = getSelectionRollupState("root", rootProperties, false);
			expect(rootState.hasAnySelection).toBe(true);
		});

		it("should keep root in selectionMap even when ALL children are deselected (sticky parent)", () => {
			const schema = ref(multipleSiblingModelsSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(false);
			const { selectionMap, onToggleAll, fragmentSelector } = useFragmentSelection(schema, mode, recursive);

			// Select both Provider and Facility
			onToggleAll({ path: "root.provider", selectAll: true });
			onToggleAll({ path: "root.facility", selectAll: true });

			// Deselect Provider
			onToggleAll({ path: "root.provider", selectAll: false });

			// Root should still exist with facility
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.has("facility")).toBe(true);

			// Deselect Facility - root still remains (sticky parent chain)
			onToggleAll({ path: "root.facility", selectAll: false });

			// Root should STILL be in selectionMap (parent chain is sticky)
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.size).toBe(0);
			// Fragment shows root is included but has no children
			expect(fragmentSelector.value).toEqual({ type: "object" });
		});

		it("should preserve parent chain when deselecting a deeply nested node if siblings remain", () => {
			const schema = ref(deepSchema);
			const mode = ref<SelectionMode>("by-property");
			const recursive = ref(true);
			const { selectionMap, onToggleAll, onToggleProperty } = useFragmentSelection(schema, mode, recursive);

			// Select the entire hospitals subtree
			onToggleAll({ path: "root.hospitals", selectAll: true });

			// Also select a root-level scalar
			onToggleProperty({ path: "root", propertyName: "systemId" });

			// Verify root has both
			expect(selectionMap.get("root")?.has("systemId")).toBe(true);
			expect(selectionMap.get("root")?.has("hospitals")).toBe(true);

			// Deselect hospitals
			onToggleAll({ path: "root.hospitals", selectAll: false });

			// Root should STILL exist because systemId is still selected
			expect(selectionMap.has("root")).toBe(true);
			expect(selectionMap.get("root")?.has("systemId")).toBe(true);
			expect(selectionMap.get("root")?.has("hospitals") ?? false).toBe(false);
		});
	});
});
