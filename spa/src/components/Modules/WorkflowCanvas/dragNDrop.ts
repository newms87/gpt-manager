// Handle drag start event
import { addWorkflowNode } from "@/components/Modules/TaskWorkflows/store";
import { TaskDefinition } from "@/types";
import { useVueFlow } from "@vue-flow/core";
import { ref } from "vue";

const draggingTaskDefinition = ref(null);
export function onDragStart(event: DragEvent, task: TaskDefinition) {
	if (event.dataTransfer) {
		draggingTaskDefinition.value = task;
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
	if (!viewportRef.value || !draggingTaskDefinition.value) return;

	// Calculate the drop position in the viewport
	const rect = viewportRef.value.getBoundingClientRect();
	const position = project({
		x: event.clientX - rect.left,
		y: event.clientY - rect.top
	});

	await addWorkflowNode(draggingTaskDefinition.value, {
		settings: { x: position.x, y: position.y }
	});
}
