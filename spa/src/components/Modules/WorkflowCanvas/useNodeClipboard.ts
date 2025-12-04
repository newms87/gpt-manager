import { apiUrls } from "@/api";
import { WorkflowNode } from "@/types";
import { useVueFlow } from "@vue-flow/core";
import { FlashMessages, request } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref, Ref } from "vue";

interface ClipboardData {
	type: "workflow-node-clipboard";
	version: string;
	nodes: ClipboardNode[];
	connections: ClipboardConnection[];
	definitions: Record<string, Record<string, unknown>>;
}

interface ClipboardNode {
	export_key: string;
	name: string;
	settings: { x: number; y: number };
	params: Record<string, unknown> | null;
	task_definition_ref: string;
}

interface ClipboardConnection {
	source_export_key: string;
	target_export_key: string;
	name: string | null;
	source_output_port: string | null;
	target_input_port: string | null;
}

interface PasteResult {
	success: boolean;
	nodes?: WorkflowNode[];
	error?: string;
}

/**
 * Composable for handling workflow node copy/paste operations via system clipboard
 *
 * Features:
 * - Copy selected nodes to system clipboard with their connections
 * - Paste nodes at mouse position with automatic coordinate conversion
 * - Track mouse position for accurate paste placement
 * - Handle clipboard permissions and errors gracefully
 *
 * @param instanceId - Vue Flow instance ID for coordinate projection
 * @param workflowDefinitionId - Current workflow definition ID (reactive ref)
 * @returns Object with clipboard operations and state
 */
export function useNodeClipboard(
	instanceId: string,
	workflowDefinitionId: Ref<number | undefined>
) {
	// Track mouse position for paste placement
	const mousePosition = ref({ x: 0, y: 0 });

	// Loading states
	const isCopying = ref(false);
	const isPasting = ref(false);

	// Computed state for convenience
	const isOperating = computed(() => isCopying.value || isPasting.value);

	/**
	 * Updates the tracked mouse position
	 * Called on every mousemove event
	 */
	function updateMousePosition(event: MouseEvent) {
		mousePosition.value = { x: event.clientX, y: event.clientY };
	}

	/**
	 * Converts screen coordinates to canvas coordinates using Vue Flow's project()
	 * Takes into account the canvas viewport, zoom level, and pan offset
	 *
	 * @returns Canvas coordinates where nodes should be pasted
	 */
	function getCanvasPosition(): { x: number; y: number } {
		const { project, vueFlowRef } = useVueFlow({ id: instanceId });

		if (!vueFlowRef.value) {
			console.warn("Vue Flow ref not available, using default position");
			return { x: 0, y: 0 };
		}

		const rect = vueFlowRef.value.getBoundingClientRect();
		return project({
			x: mousePosition.value.x - rect.left,
			y: mousePosition.value.y - rect.top
		});
	}

	/**
	 * Copies currently selected nodes to the system clipboard
	 * Includes node data, connections between selected nodes, and task definitions
	 *
	 * @returns Promise<boolean> - True if copy was successful
	 */
	async function copySelectedNodes(): Promise<boolean> {
		const { getSelectedNodes } = useVueFlow({ id: instanceId });
		const selectedNodes = getSelectedNodes.value;

		if (selectedNodes.length === 0) {
			FlashMessages.warning("No nodes selected to copy");
			return false;
		}

		const nodeIds = selectedNodes.map(n => parseInt(n.id));

		try {
			isCopying.value = true;

			// Call backend to export nodes with their connections and definitions
			const response = await request.post(`${apiUrls.workflows.nodes}/clipboard-export`, {
				node_ids: nodeIds
			});

			// Write the clipboard data as JSON to system clipboard
			await navigator.clipboard.writeText(JSON.stringify(response));

			FlashMessages.success(`Copied ${nodeIds.length} node(s) to clipboard`);
			return true;
		} catch (error: any) {
			console.error("Failed to copy nodes:", error);

			if (error.name === "NotAllowedError") {
				FlashMessages.error("Clipboard access denied. Please allow clipboard access.");
			} else {
				FlashMessages.error("Failed to copy nodes");
			}

			return false;
		} finally {
			isCopying.value = false;
		}
	}

	/**
	 * Pastes nodes from the system clipboard at the current mouse position
	 * Validates clipboard data format and converts coordinates to canvas space
	 *
	 * @returns Promise<PasteResult> - Result object with success status and pasted nodes
	 */
	async function pasteNodes(): Promise<PasteResult> {
		if (!workflowDefinitionId.value) {
			return { success: false, error: "No workflow selected" };
		}

		if (isPasting.value) {
			return { success: false, error: "Paste already in progress" };
		}

		try {
			isPasting.value = true;

			// Read clipboard text
			const clipboardText = await navigator.clipboard.readText();

			// Parse and validate clipboard data
			let clipboardData: ClipboardData;
			try {
				clipboardData = JSON.parse(clipboardText);
			} catch {
				return { success: false, error: "Clipboard does not contain valid data" };
			}

			// Validate clipboard data type
			if (clipboardData.type !== "workflow-node-clipboard") {
				return { success: false, error: "Clipboard does not contain workflow nodes" };
			}

			// Get the canvas position where nodes should be pasted
			const pastePosition = getCanvasPosition();

			// Call backend to import nodes at the calculated position
			const response = await request.post(`${apiUrls.workflows.nodes}/clipboard-import`, {
				workflow_definition_id: workflowDefinitionId.value,
				clipboard_data: clipboardData,
				paste_position: pastePosition
			});

			const nodes = response.nodes as WorkflowNode[];
			FlashMessages.success(`Pasted ${nodes.length} node(s)`);

			return { success: true, nodes };
		} catch (error: any) {
			console.error("Failed to paste nodes:", error);

			// Handle specific error types
			if (error.name === "NotAllowedError") {
				return { success: false, error: "Clipboard access denied. Please allow clipboard access." };
			}

			return { success: false, error: error.message || "Failed to paste nodes" };
		} finally {
			isPasting.value = false;
		}
	}

	/**
	 * Checks if an input element is currently focused
	 * Used to prevent clipboard shortcuts from triggering when typing
	 *
	 * @returns boolean - True if an input element has focus
	 */
	function isInputFocused(): boolean {
		const active = document.activeElement;
		return (
			active?.tagName === "INPUT" ||
			active?.tagName === "TEXTAREA" ||
			active?.getAttribute("contenteditable") === "true"
		);
	}

	// Register mouse position tracking on mount
	onMounted(() => {
		document.addEventListener("mousemove", updateMousePosition);
	});

	// Clean up event listener on unmount
	onUnmounted(() => {
		document.removeEventListener("mousemove", updateMousePosition);
	});

	return {
		// State
		mousePosition,
		isCopying,
		isPasting,
		isOperating,

		// Methods
		copySelectedNodes,
		pasteNodes,
		isInputFocused,
		getCanvasPosition
	};
}
