import { TaskDefinition, TaskRun, TaskRunner } from "@/types/task-definitions";
import { WorkflowRunStatuses } from "@/types/workflows";
import { ActionTargetItem, AnyObject, ListControlsRoutes } from "quasar-ui-danx";

export interface TaskWorkflow extends ActionTargetItem {
	id: string;
	name: string;
	description: string;
	created_at: string;
	nodes?: TaskWorkflowNode[];
	connections?: TaskWorkflowConnection[];
	runs?: TaskWorkflowRun[];
}

export interface TaskWorkflowNode extends ActionTargetItem {
	id: string;
	name: string;
	settings?: TaskWorkflowNodeSettings;
	params?: AnyObject;
	task_definition_id: number;
	taskDefinition: TaskDefinition;
	connections?: TaskWorkflowConnection[];
}

export interface TaskWorkflowConnection extends ActionTargetItem {
	id: string;
	sourceNode?: TaskWorkflowNode;
	targetNode?: TaskWorkflowNode;
	source_node_id: number;
	target_node_id: number;
	source_output_port: string;
	target_input_port: string;
	name: string;
}

export interface TaskWorkflowNodeSettings {
	x: number;
	y: number;
}

export interface TaskWorkflowRun extends TaskRunner {
	taskRuns?: TaskRun[];
}

export interface TaskWorkflowRunRoutes extends ListControlsRoutes<TaskWorkflowRun> {
	runStatuses(filter: AnyObject): Promise<WorkflowRunStatuses>;
}
