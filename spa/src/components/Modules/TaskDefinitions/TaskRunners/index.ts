import { AgentThreadTaskRunnerConfig, BaseTaskRunnerConfig, RunWorkflowTaskRunnerConfig } from "./Configs";
import {
	AgentThreadTaskRunnerNode,
	BaseTaskRunnerNode,
	ImageToTextTaskRunnerNode,
	LoadFromDatabaseTaskRunnerNode,
	PageOrganizerTaskRunnerNode,
	RunWorkflowTaskRunnerNode,
	SaveToDatabaseTaskRunnerNode,
	SplitByFileTaskRunnerNode
} from "./Nodes";

export const TaskRunners = {
	"AI Agent": {
		node: AgentThreadTaskRunnerNode,
		config: AgentThreadTaskRunnerConfig
	},
	"Base": {
		node: BaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Image To Text Transcoder": {
		node: ImageToTextTaskRunnerNode,
		config: AgentThreadTaskRunnerConfig
	},
	"Load From Database": {
		node: LoadFromDatabaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Pages Organizer": {
		node: PageOrganizerTaskRunnerNode,
		config: AgentThreadTaskRunnerConfig
	},
	"Run Workflow": {
		node: RunWorkflowTaskRunnerNode,
		config: RunWorkflowTaskRunnerConfig
	},
	"Save To Database": {
		node: SaveToDatabaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Split By File": {
		node: SplitByFileTaskRunnerNode,
		config: BaseTaskRunnerConfig
	}
};
