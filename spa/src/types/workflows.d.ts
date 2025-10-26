import { TaskDefinition, TaskRun, TaskRunner } from "@/types";
import { ActionTargetItem, AnyObject, ListControlsRoutes } from "quasar-ui-danx";

export interface WorkflowDefinition extends ActionTargetItem {
	name: string;
	description: string;
	max_workers?: number;
	created_at: string;
	nodes?: WorkflowNode[];
	connections?: WorkflowConnection[];
	runs?: WorkflowRun[];
}

export interface WorkflowNode extends ActionTargetItem {
	name: string;
	settings?: WorkflowNodeSettings;
	params?: AnyObject;
	task_definition_id: number;
	taskDefinition: TaskDefinition;
	connectionsAsTarget?: WorkflowConnection[];
	connectionsAsSource?: WorkflowConnection[];
}

export interface WorkflowConnection extends ActionTargetItem {
	sourceNode?: WorkflowNode;
	targetNode?: WorkflowNode;
	source_node_id: number;
	target_node_id: number;
	source_output_port: string;
	target_input_port: string;
	name: string;
}

export interface WorkflowNodeSettings {
	x: number;
	y: number;
}

export interface WorkflowRun extends TaskRunner {
	workflow_definition_id?: number;
	active_workers_count?: number;
	error_count?: number;
	taskRuns?: TaskRun[];
	progress_percent?: number;
	total_nodes?: number;
	completed_tasks?: number;
}

export interface WorkflowDefinitionRoutes extends ListControlsRoutes<WorkflowDefinition> {
	exportToJson(workflowDefinition: WorkflowDefinition): Promise<AnyObject>;

	importFromJson(workflowDefinitionJson: AnyObject): Promise<WorkflowDefinition>;
}

export interface WorkflowRunRoutes extends ListControlsRoutes<WorkflowRun> {
	runStatuses(filter: AnyObject): Promise<WorkflowRunStatuses>;
}

export interface WorkflowRunStatuses {
	total_count: number;
	completed_count: number;
	failed_count: number;
	pending_count: number;
	running_count: number;
}
