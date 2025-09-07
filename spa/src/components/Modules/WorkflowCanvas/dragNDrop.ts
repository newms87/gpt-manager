// Handle drag start event
import { addWorkflowNode } from "@/components/Modules/WorkflowDefinitions/store";
import { TaskDefinition, TaskRunnerClass } from "@/types";
import { useVueFlow } from "@vue-flow/core";
import { ref } from "vue";

const draggingNodeItem = ref(null);
export function onDragStart(event: DragEvent, nodeItem: TaskRunnerClass | TaskDefinition) {
	if (event.dataTransfer) {
		draggingNodeItem.value = nodeItem;
		event.dataTransfer.effectAllowed = "move";
	}
}

export function onDragOver(event) {
	event.preventDefault();

	if (event.dataTransfer) {
		event.dataTransfer.dropEffect = "move";
	}
}

/**
 *  Handles dropping a task definition record on the workflow canvas
 */
export async function handleExternalDrop(id: string, event: DragEvent) {
	const { viewportRef, project } = useVueFlow(id);
	if (!viewportRef.value || !draggingNodeItem.value) return;

	// Calculate the drop position in the viewport
	const rect = viewportRef.value.getBoundingClientRect();
	const position = project({
		x: event.clientX - rect.left,
		y: event.clientY - rect.top
	});

	await addWorkflowNode(draggingNodeItem.value, {
		settings: { x: position.x, y: position.y }
	});
	
	// Reset dragging state
	draggingNodeItem.value = null;
}
