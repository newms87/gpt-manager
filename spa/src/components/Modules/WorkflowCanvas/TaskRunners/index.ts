import { default as AgentThreadTaskRunnerNode } from "./AgentThreadTaskRunnerNode.vue";
import { default as BaseTaskRunnerNode } from "./BaseTaskRunnerNode.vue";
import { default as ImageToTextTaskRunnerNode } from "./ImageToTextTaskRunnerNode.vue";
import { default as PageOrganizerTaskRunnerNode } from "./PageOrganizerTaskRunnerNode.vue";
import { default as SaveToDatabaseTaskRunnerNode } from "./SaveToDatabaseTaskRunnerNode.vue";
import { default as SplitByFileTaskRunnerNode } from "./SplitByFileTaskRunnerNode.vue";

export const TaskRunners = {
	"Base": BaseTaskRunnerNode,
	"AI Agent": AgentThreadTaskRunnerNode,
	"Pages Organizer": PageOrganizerTaskRunnerNode,
	"Image To Text Transcoder": ImageToTextTaskRunnerNode,
	"Split By File": SplitByFileTaskRunnerNode,
	"Save To Database": SaveToDatabaseTaskRunnerNode
};
export {
	AgentThreadTaskRunnerNode,
	BaseTaskRunnerNode,
	ImageToTextTaskRunnerNode,
	PageOrganizerTaskRunnerNode,
	SaveToDatabaseTaskRunnerNode,
	SplitByFileTaskRunnerNode
};
