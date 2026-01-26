import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { h, defineComponent } from "vue";
import FragmentModelNode from "../FragmentModelNode.vue";
import { FragmentModelNodeData } from "../types";

// Create mock QCheckbox that behaves like the real one
const MockQCheckbox = defineComponent({
	name: "QCheckbox",
	props: ["modelValue", "indeterminateValue", "size", "color", "dark"],
	emits: ["update:modelValue"],
	setup(props, { emit }) {
		return () =>
			h("input", {
				type: "checkbox",
				class: "mock-q-checkbox",
				checked: props.modelValue === true,
				"data-indeterminate": props.modelValue === null ? "true" : "false",
				"data-value": String(props.modelValue),
				onClick: () => emit("update:modelValue", !props.modelValue)
			});
	}
});

// Mock the FragmentModelNodeHeader component to expose the checkbox for testing
const MockFragmentModelNodeHeader = defineComponent({
	name: "FragmentModelNodeHeader",
	props: ["title", "description", "editEnabled", "selectionEnabled", "isRoot", "checkboxValue", "shouldFocus"],
	emits: ["toggle-all", "update-model", "remove-model"],
	setup(props, { emit }) {
		return () =>
			h("div", { class: "mock-header" }, [
				props.selectionEnabled
					? h(MockQCheckbox, {
							modelValue: props.checkboxValue,
							indeterminateValue: null,
							"onUpdate:modelValue": () => emit("toggle-all")
						})
					: null,
				h("span", { class: "mock-title" }, props.title)
			]);
	}
});

// Mock the other sub-components
const MockFragmentModelNodeHandles = defineComponent({
	name: "FragmentModelNodeHandles",
	props: ["type", "direction", "isArray", "hasModelChildren", "editEnabled", "isRoot"],
	setup() {
		return () => h("div", { class: "mock-handles" });
	}
});

const MockFragmentModelNodeFooter = defineComponent({
	name: "FragmentModelNodeFooter",
	props: ["editEnabled"],
	emits: ["add-property"],
	setup() {
		return () => h("div", { class: "mock-footer" });
	}
});

const MockFragmentModelNodeAddButton = defineComponent({
	name: "FragmentModelNodeAddButton",
	props: ["editEnabled", "direction"],
	emits: ["add-child-model"],
	setup() {
		return () => h("div", { class: "mock-add-button" });
	}
});

const MockFragmentPropertyRow = defineComponent({
	name: "FragmentPropertyRow",
	props: ["name", "property", "editActive", "selectionActive", "isSelected", "showDescription"],
	emits: ["toggle", "update-name", "update-type", "remove"],
	setup(props) {
		return () => h("div", { class: "mock-property-row" }, props.name);
	}
});

/**
 * Helper to create FragmentModelNodeData for testing
 */
function createNodeData(overrides: Partial<FragmentModelNodeData> = {}): FragmentModelNodeData {
	return {
		name: "TestNode",
		path: "root",
		schema: { type: "object", properties: {} },
		properties: [],
		selectedProperties: [],
		hasAnySelection: false,
		isFullySelected: false,
		isIncluded: false,
		selectionMode: "by-property",
		selectionEnabled: true, // Enable selection to show header checkbox
		direction: "LR",
		showProperties: false,
		...overrides
	};
}

/**
 * Mount FragmentModelNode with mocked dependencies
 */
function mountComponent(data: FragmentModelNodeData) {
	return mount(FragmentModelNode, {
		props: { data },
		global: {
			stubs: {
				FragmentModelNodeHeader: MockFragmentModelNodeHeader,
				FragmentModelNodeHandles: MockFragmentModelNodeHandles,
				FragmentModelNodeFooter: MockFragmentModelNodeFooter,
				FragmentModelNodeAddButton: MockFragmentModelNodeAddButton,
				FragmentPropertyRow: MockFragmentPropertyRow
			}
		}
	});
}

describe("FragmentModelNode", () => {
	// =========================================================================
	// Bug 1 & 2: Checkbox indeterminate state tests
	// =========================================================================
	describe("Checkbox indeterminate state", () => {
		it("should show indeterminate checkbox when hasAnySelection but not isFullySelected", () => {
			const data = createNodeData({
				hasAnySelection: true,
				isFullySelected: false,
				selectionMode: "by-property"
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			// Verify the checkbox has indeterminate (null) value
			expect(checkbox.attributes("data-value")).toBe("null");
			expect(checkbox.attributes("data-indeterminate")).toBe("true");
		});

		it("should show checked checkbox when isFullySelected", () => {
			const data = createNodeData({
				hasAnySelection: true,
				isFullySelected: true,
				selectionMode: "by-property"
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			// Verify the checkbox is checked (true value)
			expect(checkbox.attributes("data-value")).toBe("true");
			expect(checkbox.attributes("data-indeterminate")).toBe("false");
		});

		it("should show unchecked checkbox when no selection", () => {
			const data = createNodeData({
				hasAnySelection: false,
				isFullySelected: false,
				selectionMode: "by-property"
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			// Verify the checkbox is unchecked (false value)
			expect(checkbox.attributes("data-value")).toBe("false");
			expect(checkbox.attributes("data-indeterminate")).toBe("false");
		});

		it("should transition from indeterminate to checked when fully selected", () => {
			// First mount with partial selection
			const partialData = createNodeData({
				hasAnySelection: true,
				isFullySelected: false,
				selectionMode: "by-property"
			});

			const wrapper = mountComponent(partialData);
			let checkbox = wrapper.find(".mock-q-checkbox");
			expect(checkbox.attributes("data-value")).toBe("null");

			// Now update to fully selected
			const fullData = createNodeData({
				hasAnySelection: true,
				isFullySelected: true,
				selectionMode: "by-property"
			});

			const wrapper2 = mountComponent(fullData);
			checkbox = wrapper2.find(".mock-q-checkbox");
			expect(checkbox.attributes("data-value")).toBe("true");
		});
	});

	// =========================================================================
	// Bug 4: By-Model mode deselect tests
	// =========================================================================
	describe("By-Model mode toggle", () => {
		it("should emit selectAll: false when clicking checked by-model checkbox", async () => {
			const data = createNodeData({
				selectionMode: "by-model",
				isIncluded: true,
				hasAnySelection: false,
				isFullySelected: false
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			// Click the checkbox
			await checkbox.trigger("click");

			// Verify emit was called with selectAll: false (to deselect)
			const emitted = wrapper.emitted("toggle-all");
			expect(emitted).toBeTruthy();
			expect(emitted![0]).toEqual([{ path: "root", selectAll: false }]);
		});

		it("should emit selectAll: true when clicking unchecked by-model checkbox", async () => {
			const data = createNodeData({
				selectionMode: "by-model",
				isIncluded: false,
				hasAnySelection: false,
				isFullySelected: false
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			// Click the checkbox
			await checkbox.trigger("click");

			// Verify emit was called with selectAll: true (to select)
			const emitted = wrapper.emitted("toggle-all");
			expect(emitted).toBeTruthy();
			expect(emitted![0]).toEqual([{ path: "root", selectAll: true }]);
		});

		it("should use isIncluded for checkbox value in by-model mode", () => {
			// When isIncluded is true
			const includedData = createNodeData({
				selectionMode: "by-model",
				isIncluded: true
			});

			const wrapper1 = mountComponent(includedData);
			const checkbox1 = wrapper1.find(".mock-q-checkbox");
			expect(checkbox1.attributes("data-value")).toBe("true");

			// When isIncluded is false
			const notIncludedData = createNodeData({
				selectionMode: "by-model",
				isIncluded: false
			});

			const wrapper2 = mountComponent(notIncludedData);
			const checkbox2 = wrapper2.find(".mock-q-checkbox");
			expect(checkbox2.attributes("data-value")).toBe("false");
		});

		it("should not show indeterminate state in by-model mode", () => {
			// Even with hasAnySelection true, by-model mode should not be indeterminate
			const data = createNodeData({
				selectionMode: "by-model",
				isIncluded: false,
				hasAnySelection: true,
				isFullySelected: false
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			// In by-model mode, checkbox should be false (not indeterminate)
			// because it uses isIncluded, not hasAnySelection
			expect(checkbox.attributes("data-value")).toBe("false");
			expect(checkbox.attributes("data-indeterminate")).toBe("false");
		});
	});

	// =========================================================================
	// Additional checkbox behavior tests
	// =========================================================================
	describe("Checkbox behavior in different modes", () => {
		it("should use isFullySelected for by-property mode", () => {
			const data = createNodeData({
				selectionMode: "by-property",
				hasAnySelection: true,
				isFullySelected: true
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");
			expect(checkbox.attributes("data-value")).toBe("true");
		});

		it("should emit toggle-all with correct path", async () => {
			const data = createNodeData({
				path: "root.patient.address",
				selectionMode: "by-property",
				hasAnySelection: false,
				isFullySelected: false
			});

			const wrapper = mountComponent(data);
			const checkbox = wrapper.find(".mock-q-checkbox");

			await checkbox.trigger("click");

			const emitted = wrapper.emitted("toggle-all");
			expect(emitted).toBeTruthy();
			expect(emitted![0]).toEqual([{ path: "root.patient.address", selectAll: true }]);
		});
	});
});
