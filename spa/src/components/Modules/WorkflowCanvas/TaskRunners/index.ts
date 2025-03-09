import { default as AgentThreadTaskRunnerNode } from "./AgentThreadTaskRunnerNode.vue";
import { default as BaseTaskRunnerNode } from "./BaseTaskRunnerNode.vue";
import { default as ImageToTextTaskRunnerNode } from "./ImageToTextTaskRunnerNode.vue";
import { default as LoadFromDatabaseTaskRunnerNode } from "./LoadFromDatabaseTaskRunnerNode.vue";
import { default as PageOrganizerTaskRunnerNode } from "./PageOrganizerTaskRunnerNode.vue";
import { default as SaveToDatabaseTaskRunnerNode } from "./SaveToDatabaseTaskRunnerNode.vue";
import { default as SplitByFileTaskRunnerNode } from "./SplitByFileTaskRunnerNode.vue";

export const TaskRunners = {
	"AI Agent": AgentThreadTaskRunnerNode,
	"Base": BaseTaskRunnerNode,
	"Image To Text Transcoder": ImageToTextTaskRunnerNode,
	"Load From Database": LoadFromDatabaseTaskRunnerNode,
	"Pages Organizer": PageOrganizerTaskRunnerNode,
	"Save To Database": SaveToDatabaseTaskRunnerNode,
	"Split By File": SplitByFileTaskRunnerNode
};
export {
	AgentThreadTaskRunnerNode,
	BaseTaskRunnerNode,
	ImageToTextTaskRunnerNode,
	LoadFromDatabaseTaskRunnerNode,
	PageOrganizerTaskRunnerNode,
	SaveToDatabaseTaskRunnerNode,
	SplitByFileTaskRunnerNode
};
