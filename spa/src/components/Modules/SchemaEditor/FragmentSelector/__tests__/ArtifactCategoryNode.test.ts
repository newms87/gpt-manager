import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { h, defineComponent } from "vue";
import ArtifactCategoryNode from "../ArtifactCategoryNode.vue";
import { ArtifactCategoryNodeData } from "../types";
import { ArtifactCategoryDefinition } from "@/types";

// Mock VueFlow Handle component - required since Handle needs VueFlow context
const MockHandle = defineComponent({
	name: "Handle",
	props: ["id", "type", "position", "class"],
	setup(props, { slots }) {
		return () =>
			h(
				"div",
				{
					class: `mock-handle ${props.class || ""}`,
					"data-handle-id": props.id,
					"data-handle-type": props.type,
					"data-handle-position": props.position
				},
				slots.default?.()
			);
	}
});

// Mock AcdIndicatorDot component
const MockAcdIndicatorDot = defineComponent({
	name: "AcdIndicatorDot",
	props: ["direction"],
	setup(props) {
		return () => h("div", { class: "mock-acd-indicator-dot", "data-direction": props.direction });
	}
});

// Mock ActionButton component
const MockActionButton = defineComponent({
	name: "ActionButton",
	props: ["icon", "color", "size", "tooltip"],
	emits: ["click"],
	setup(props, { emit }) {
		return () =>
			h("button", {
				class: `mock-action-button mock-action-button-${props.color}`,
				"data-tooltip": props.tooltip,
				onClick: () => emit("click")
			});
	}
});

/**
 * Create a test ACD for use in tests.
 */
function createTestAcd(
	overrides: Partial<ArtifactCategoryDefinition> = {}
): ArtifactCategoryDefinition {
	return {
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
	};
}

/**
 * Create node data for ArtifactCategoryNode.
 */
function createNodeData(
	overrides: Partial<ArtifactCategoryNodeData> = {}
): ArtifactCategoryNodeData {
	return {
		acd: createTestAcd(),
		direction: "LR",
		parentModelPath: "root",
		...overrides
	};
}

/**
 * Mount ArtifactCategoryNode with mocked dependencies.
 */
function mountComponent(data: ArtifactCategoryNodeData) {
	return mount(ArtifactCategoryNode, {
		props: { data },
		global: {
			stubs: {
				Handle: MockHandle,
				AcdIndicatorDot: MockAcdIndicatorDot,
				ActionButton: MockActionButton
			}
		}
	});
}

describe("ArtifactCategoryNode", () => {
	// =========================================================================
	// Rendering Tests
	// =========================================================================
	describe("rendering", () => {
		it("should render ACD label and name", () => {
			const data = createNodeData({
				acd: createTestAcd({
					label: "Patient Summary",
					name: "summary"
				})
			});

			const wrapper = mountComponent(data);

			// Label should be displayed in header
			expect(wrapper.text()).toContain("Patient Summary");
			// Name should be displayed as subtitle when different from label
			expect(wrapper.text()).toContain("summary");
		});

		it("should render only name when label equals name", () => {
			const data = createNodeData({
				acd: createTestAcd({
					label: "unique_test_name",
					name: "unique_test_name",
					prompt: "A test prompt without the name in it."
				})
			});

			const wrapper = mountComponent(data);
			const textContent = wrapper.text();

			// Should only show the name once (as label) since name === label
			const matches = textContent.match(/unique_test_name/g);
			expect(matches?.length).toBe(1);
		});

		it("should render only name when label is empty", () => {
			const data = createNodeData({
				acd: createTestAcd({
					label: "",
					name: "summary"
				})
			});

			const wrapper = mountComponent(data);

			// Should show name as primary display
			expect(wrapper.text()).toContain("summary");
		});

		it("should show prompt preview truncated to 3 lines", () => {
			const longPrompt =
				"This is a very long prompt that should be truncated. It contains multiple sentences to ensure it exceeds three lines when displayed. The component uses line-clamp-3 CSS class to handle this truncation automatically.";

			const data = createNodeData({
				acd: createTestAcd({ prompt: longPrompt })
			});

			const wrapper = mountComponent(data);
			const promptElement = wrapper.find(".line-clamp-3");

			expect(promptElement.exists()).toBe(true);
			expect(promptElement.text()).toContain(longPrompt);
		});

		it("should show 'No prompt defined' when prompt is empty", () => {
			const data = createNodeData({
				acd: createTestAcd({ prompt: "" })
			});

			const wrapper = mountComponent(data);

			expect(wrapper.text()).toContain("No prompt defined");
		});

		it("should show 'No prompt defined' when prompt is null-like", () => {
			const data = createNodeData({
				acd: createTestAcd({ prompt: "" })
			});

			const wrapper = mountComponent(data);

			expect(wrapper.text()).toContain("No prompt defined");
		});

		it("should show selection summary badge when fragment_selector has selections", () => {
			const data = createNodeData({
				acd: createTestAcd({
					fragment_selector: {
						type: "object",
						children: {
							patient: { type: "object" },
							recordId: { type: "string" }
						}
					}
				})
			});

			const wrapper = mountComponent(data);

			// Should show count of selections
			expect(wrapper.text()).toContain("2 selections");
		});

		it("should show singular 'selection' for single selection", () => {
			const data = createNodeData({
				acd: createTestAcd({
					fragment_selector: {
						type: "object",
						children: {
							patient: { type: "object" }
						}
					}
				})
			});

			const wrapper = mountComponent(data);

			expect(wrapper.text()).toContain("1 selection");
		});

		it("should count nested selections in summary", () => {
			const data = createNodeData({
				acd: createTestAcd({
					fragment_selector: {
						type: "object",
						children: {
							patient: {
								type: "object",
								children: {
									name: { type: "string" },
									dob: { type: "string" }
								}
							}
						}
					}
				})
			});

			const wrapper = mountComponent(data);

			// 1 (patient) + 2 (name, dob) = 3 selections
			expect(wrapper.text()).toContain("3 selections");
		});

		it("should not show selection summary when fragment_selector is null", () => {
			const data = createNodeData({
				acd: createTestAcd({ fragment_selector: null })
			});

			const wrapper = mountComponent(data);

			expect(wrapper.text()).not.toContain("selection");
		});

		it("should not show selection summary when fragment_selector has no children", () => {
			const data = createNodeData({
				acd: createTestAcd({
					fragment_selector: { type: "object" }
				})
			});

			const wrapper = mountComponent(data);

			expect(wrapper.text()).not.toContain("selection");
		});

		it("should not show selection summary when children is empty object", () => {
			const data = createNodeData({
				acd: createTestAcd({
					fragment_selector: {
						type: "object",
						children: {}
					}
				})
			});

			const wrapper = mountComponent(data);

			expect(wrapper.text()).not.toContain("selection");
		});
	});

	// =========================================================================
	// Edit Mode Tests
	// =========================================================================
	describe("edit mode", () => {
		it("should show edit/delete buttons when editEnabled is true", () => {
			const data = createNodeData({
				editEnabled: true,
				acd: createTestAcd({ deletable: true })
			});

			const wrapper = mountComponent(data);

			const buttons = wrapper.findAll(".mock-action-button");
			expect(buttons.length).toBe(2); // Edit and Delete
		});

		it("should hide edit/delete buttons when editEnabled is false", () => {
			const data = createNodeData({
				editEnabled: false,
				acd: createTestAcd({ deletable: true })
			});

			const wrapper = mountComponent(data);

			const buttons = wrapper.findAll(".mock-action-button");
			expect(buttons.length).toBe(0);
		});

		it("should hide edit/delete buttons when editEnabled is undefined", () => {
			const data = createNodeData({
				acd: createTestAcd({ deletable: true })
			});
			// Explicitly remove editEnabled
			delete data.editEnabled;

			const wrapper = mountComponent(data);

			const buttons = wrapper.findAll(".mock-action-button");
			expect(buttons.length).toBe(0);
		});

		it("should hide delete button when acd.deletable is false", () => {
			const data = createNodeData({
				editEnabled: true,
				acd: createTestAcd({ deletable: false })
			});

			const wrapper = mountComponent(data);

			const buttons = wrapper.findAll(".mock-action-button");
			expect(buttons.length).toBe(1); // Only Edit button

			// Verify it's the edit button
			const editButton = wrapper.find('[data-tooltip="Edit Artifact Category"]');
			expect(editButton.exists()).toBe(true);

			const deleteButton = wrapper.find('[data-tooltip="Delete Artifact Category"]');
			expect(deleteButton.exists()).toBe(false);
		});

		it("should show edit button with violet color", () => {
			const data = createNodeData({
				editEnabled: true,
				acd: createTestAcd({ deletable: false })
			});

			const wrapper = mountComponent(data);

			const editButton = wrapper.find(".mock-action-button-violet");
			expect(editButton.exists()).toBe(true);
		});

		it("should show delete button with red color", () => {
			const data = createNodeData({
				editEnabled: true,
				acd: createTestAcd({ deletable: true })
			});

			const wrapper = mountComponent(data);

			const deleteButton = wrapper.find(".mock-action-button-red");
			expect(deleteButton.exists()).toBe(true);
		});
	});

	// =========================================================================
	// Events Tests
	// =========================================================================
	describe("events", () => {
		it("should emit edit event with ACD when edit button clicked", async () => {
			const acd = createTestAcd({ id: "test-acd-123" });
			const data = createNodeData({
				editEnabled: true,
				acd
			});

			const wrapper = mountComponent(data);

			const editButton = wrapper.find('[data-tooltip="Edit Artifact Category"]');
			await editButton.trigger("click");

			const emitted = wrapper.emitted("edit");
			expect(emitted).toBeTruthy();
			expect(emitted).toHaveLength(1);
			expect(emitted![0]).toEqual([acd]);
		});

		it("should emit delete event with ACD when delete button clicked", async () => {
			const acd = createTestAcd({ id: "test-acd-456", deletable: true });
			const data = createNodeData({
				editEnabled: true,
				acd
			});

			const wrapper = mountComponent(data);

			const deleteButton = wrapper.find('[data-tooltip="Delete Artifact Category"]');
			await deleteButton.trigger("click");

			const emitted = wrapper.emitted("delete");
			expect(emitted).toBeTruthy();
			expect(emitted).toHaveLength(1);
			expect(emitted![0]).toEqual([acd]);
		});

		it("should not emit events when buttons are not visible", () => {
			const data = createNodeData({
				editEnabled: false
			});

			const wrapper = mountComponent(data);

			// No buttons should exist
			const buttons = wrapper.findAll(".mock-action-button");
			expect(buttons.length).toBe(0);

			// No events should be emitted
			expect(wrapper.emitted("edit")).toBeFalsy();
			expect(wrapper.emitted("delete")).toBeFalsy();
		});
	});

	// =========================================================================
	// Layout Direction Tests
	// =========================================================================
	describe("layout direction", () => {
		it("should position handle correctly for LR direction", () => {
			const data = createNodeData({ direction: "LR" });

			const wrapper = mountComponent(data);

			// Left handle should be visible in LR mode
			const leftHandle = wrapper.find('[data-handle-id="target-left"]');
			expect(leftHandle.exists()).toBe(true);
			expect(leftHandle.classes()).not.toContain("!opacity-0");

			// Top handle should be hidden in LR mode
			const topHandle = wrapper.find('[data-handle-id="target-top"]');
			expect(topHandle.exists()).toBe(true);
			expect(topHandle.classes()).toContain("!opacity-0");
		});

		it("should position handle correctly for TB direction", () => {
			const data = createNodeData({ direction: "TB" });

			const wrapper = mountComponent(data);

			// Top handle should be visible in TB mode
			const topHandle = wrapper.find('[data-handle-id="target-top"]');
			expect(topHandle.exists()).toBe(true);
			expect(topHandle.classes()).not.toContain("!opacity-0");

			// Left handle should be hidden in TB mode
			const leftHandle = wrapper.find('[data-handle-id="target-left"]');
			expect(leftHandle.exists()).toBe(true);
			expect(leftHandle.classes()).toContain("!opacity-0");
		});

		it("should pass direction to AcdIndicatorDot components", () => {
			const data = createNodeData({ direction: "TB" });

			const wrapper = mountComponent(data);

			const indicators = wrapper.findAll(".mock-acd-indicator-dot");
			expect(indicators.length).toBe(2); // One for each handle

			for (const indicator of indicators) {
				expect(indicator.attributes("data-direction")).toBe("TB");
			}
		});
	});

	// =========================================================================
	// Styling Tests
	// =========================================================================
	describe("styling", () => {
		it("should have violet theme colors", () => {
			const data = createNodeData();

			const wrapper = mountComponent(data);

			// Check for violet background
			const container = wrapper.find(".bg-violet-900");
			expect(container.exists()).toBe(true);

			// Check for violet border
			const borderedElement = wrapper.find(".border-violet-600");
			expect(borderedElement.exists()).toBe(true);
		});

		it("should have minimum width class", () => {
			const data = createNodeData();

			const wrapper = mountComponent(data);

			const node = wrapper.find(".artifact-category-node");
			expect(node.classes()).toContain("min-w-48");
		});
	});

	// =========================================================================
	// Edge Cases
	// =========================================================================
	describe("edge cases", () => {
		it("should handle ACD with all empty/null values", () => {
			const data = createNodeData({
				acd: createTestAcd({
					label: "",
					name: "",
					prompt: "",
					fragment_selector: null
				})
			});

			// Should not throw
			const wrapper = mountComponent(data);
			expect(wrapper.exists()).toBe(true);

			// Should show fallback text
			expect(wrapper.text()).toContain("No prompt defined");
		});

		it("should handle deeply nested fragment_selector", () => {
			const data = createNodeData({
				acd: createTestAcd({
					fragment_selector: {
						type: "object",
						children: {
							level1: {
								type: "object",
								children: {
									level2: {
										type: "array",
										children: {
											level3: {
												type: "object",
												children: {
													level4: { type: "string" }
												}
											}
										}
									}
								}
							}
						}
					}
				})
			});

			const wrapper = mountComponent(data);

			// Should count all nested selections: 1 + 1 + 1 + 1 = 4
			expect(wrapper.text()).toContain("4 selections");
		});
	});
});
