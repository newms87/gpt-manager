import { default as AgentThreadTaskRunnerNode } from "./AgentThreadTaskRunnerNode.vue";
import { default as BaseTaskRunnerNode } from "./BaseTaskRunnerNode.vue";
import { default as PageOrganizerTaskRunnerNode } from "./PageOrganizerTaskRunnerNode.vue";

const TaskRunners = {
	"Base": BaseTaskRunnerNode,
	"AI Agent": AgentThreadTaskRunnerNode,
	"Pages Organizer": PageOrganizerTaskRunnerNode
};
export {
	TaskRunners,
	BaseTaskRunnerNode,
	PageOrganizerTaskRunnerNode
};
