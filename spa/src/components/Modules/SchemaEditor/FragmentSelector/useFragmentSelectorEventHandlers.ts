import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { nextTick, Ref } from "vue";

export interface FragmentSelectorEmit {
	(event: "update:modelValue", value: FragmentSelector | null): void;
	(event: "update:schema", schema: JsonSchema): void;
}

export interface SchemaEditor {
	addProperty: (path: string, type: JsonSchemaType, baseName: string) => JsonSchema;
	updateProperty: (path: string, originalName: string, newName: string, updates: Partial<JsonSchema>) => JsonSchema;
	removeProperty: (path: string, name: string) => JsonSchema;
	addChildModel: (path: string, type: "object" | "array", baseName: string) => { schema: JsonSchema; name: string };
	updateModel: (path: string, updates: Partial<JsonSchema>) => JsonSchema;
	removeModel: (path: string) => JsonSchema;
}

export interface SelectionHandlers {
	onToggleProperty: (payload: { path: string; propertyName: string }) => void;
	onToggleAll: (payload: { path: string; selectAll: boolean }) => void;
	fragmentSelector: { value: FragmentSelector };
}

export interface EventHandlersParams {
	editor: SchemaEditor;
	selection: SelectionHandlers;
	emit: FragmentSelectorEmit;
	effectiveEditEnabled: Ref<boolean>;
	focusedNodePath: Ref<string | null>;
	triggerRelayout: () => Promise<void>;
	centerOnNode: (nodeId: string, duration?: number) => void;
}

export interface FragmentSelectorEventHandlers {
	handleToggleProperty: (payload: { path: string; propertyName: string }) => void;
	handleToggleAll: (payload: { path: string; selectAll: boolean }) => void;
	handleAddProperty: (payload: { path: string; type: string; baseName: string }) => void;
	handleUpdateProperty: (payload: { path: string; originalName: string; newName: string; updates: object }) => void;
	handleRemoveProperty: (payload: { path: string; name: string }) => void;
	handleAddChildModel: (payload: { path: string; type: "object" | "array"; baseName: string }) => Promise<void>;
	handleUpdateModel: (payload: { path: string; updates: object }) => void;
	handleRemoveModel: (payload: { path: string }) => void;
}

/**
 * Composable that provides all event handlers for FragmentSelectorCanvas.
 * Bridges schema editing, selection, and layout operations.
 */
export function useFragmentSelectorEventHandlers(params: EventHandlersParams): FragmentSelectorEventHandlers {
	const { editor, selection, emit, effectiveEditEnabled, focusedNodePath, triggerRelayout, centerOnNode } = params;

	// Toggle handlers that also emit the updated selector
	function handleToggleProperty(payload: { path: string; propertyName: string }): void {
		selection.onToggleProperty(payload);
		emit("update:modelValue", selection.fragmentSelector.value);
	}

	function handleToggleAll(payload: { path: string; selectAll: boolean }): void {
		selection.onToggleAll(payload);
		emit("update:modelValue", selection.fragmentSelector.value);
	}

	// Edit mode handlers
	function handleAddProperty(payload: { path: string; type: string; baseName: string }): void {
		if (!effectiveEditEnabled.value) return;
		const newSchema = editor.addProperty(payload.path, payload.type as JsonSchemaType, payload.baseName);
		emit("update:schema", newSchema);
		nextTick(() => triggerRelayout());
	}

	function handleUpdateProperty(payload: { path: string; originalName: string; newName: string; updates: object }): void {
		if (!effectiveEditEnabled.value) return;
		const newSchema = editor.updateProperty(payload.path, payload.originalName, payload.newName, payload.updates as Partial<JsonSchema>);
		emit("update:schema", newSchema);
	}

	function handleRemoveProperty(payload: { path: string; name: string }): void {
		if (!effectiveEditEnabled.value) return;
		const newSchema = editor.removeProperty(payload.path, payload.name);
		emit("update:schema", newSchema);
		nextTick(() => triggerRelayout());
	}

	async function handleAddChildModel(payload: { path: string; type: "object" | "array"; baseName: string }): Promise<void> {
		if (!effectiveEditEnabled.value) return;
		const { schema: newSchema, name } = editor.addChildModel(payload.path, payload.type, payload.baseName);
		const newNodePath = `${payload.path}.${name}`;
		emit("update:schema", newSchema);
		await nextTick();
		await triggerRelayout();
		// Smoothly pan to the new model after layout is complete
		centerOnNode(newNodePath, 400);
		// Set focus on the new node's name input after centering animation completes
		setTimeout(() => {
			focusedNodePath.value = newNodePath;
			// Clear the focus trigger after a brief delay to allow the node to react
			setTimeout(() => {
				focusedNodePath.value = null;
			}, 100);
		}, 400);
	}

	function handleUpdateModel(payload: { path: string; updates: object }): void {
		if (!effectiveEditEnabled.value) return;
		const newSchema = editor.updateModel(payload.path, payload.updates as Partial<JsonSchema>);
		emit("update:schema", newSchema);
	}

	function handleRemoveModel(payload: { path: string }): void {
		if (!effectiveEditEnabled.value) return;
		const newSchema = editor.removeModel(payload.path);
		emit("update:schema", newSchema);
		nextTick(() => triggerRelayout());
	}

	return {
		handleToggleProperty,
		handleToggleAll,
		handleAddProperty,
		handleUpdateProperty,
		handleRemoveProperty,
		handleAddChildModel,
		handleUpdateModel,
		handleRemoveModel
	};
}
