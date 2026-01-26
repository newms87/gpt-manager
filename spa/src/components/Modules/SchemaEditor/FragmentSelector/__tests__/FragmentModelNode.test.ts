import { describe, it, expect, vi } from "vitest";
import { mount, VueWrapper } from "@vue/test-utils";
import { ref, h, defineComponent } from "vue";
import FragmentModelNode from "../FragmentModelNode.vue";
import { FragmentModelNodeData } from "../useFragmentSelectorGraph";

// Mock the VueFlow Handle component
const MockHandle = defineComponent({
	name: "Handle",
	props: ["id", "type", "position"],
	setup(props, { slots }) {
		return () => h("div", { class: "mock-handle", "data-id": props.id }, slots.default?.());
	}
});

// Mock the indicator components
const MockArrayIndicatorDots = defineComponent({
	name: "ArrayIndicatorDots",
	props: ["direction"],
	setup() {
		return () => h("div", { class: "mock-array-indicator" });
	}
});

const MockObjectIndicatorDot = defineComponent({
	name: "ObjectIndicatorDot",
	props: ["direction"],
	setup() {
		return () => h("div", { class: "mock-object-indicator" });
	}
});

const MockSourceHandleDot = defineComponent({
	name: "SourceHandleDot",
	props: ["position", "visible"],
	setup() {
		return () => h("div", { class: "mock-source-handle" });
	}
});

// Mock quasar-ui-danx components
const MockShowHideButton = defineComponent({
	name: "ShowHideButton",
	props: ["modelValue", "showIcon", "hideIcon", "size"],
	setup() {
		return () => h("button", { class: "mock-show-hide-button" });
	}
});

const MockInfoDialog = defineComponent({
	name: "InfoDialog",
	setup(_, { slots }) {
		return () => h("div", { class: "mock-info-dialog" }, slots.default?.());
	}
});

const MockMarkdownEditor = defineComponent({
	name: "MarkdownEditor",
	props: ["modelValue", "readonly", "hideFooter", "minHeight"],
	setup() {
		return () => h("div", { class: "mock-markdown-editor" });
	}
});

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
				Handle: MockHandle,
				ArrayIndicatorDots: MockArrayIndicatorDots,
				ObjectIndicatorDot: MockObjectIndicatorDot,
				SourceHandleDot: MockSourceHandleDot,
				ShowHideButton: MockShowHideButton,
				InfoDialog: MockInfoDialog,
				MarkdownEditor: MockMarkdownEditor,
				QCheckbox: MockQCheckbox
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
