import {
	AgentThreadRunnerLottie,
	ImageToTextLottie,
	LoadFromDbLottie,
	PageOrganizerLottie,
	RunWorkflowLottie,
	SaveToDbLottie,
	SplitByFileLottie,
	WorkflowInputLottie
} from "@/assets/dotlottie";
import {
	AgentThreadTaskRunnerConfig,
	BaseTaskRunnerConfig,
	ImageToTextTranscoderTaskRunnerConfig,
	PagesOrganizerTaskRunnerConfig,
	RunWorkflowTaskRunnerConfig,
	SplitArtifactsByJsonContentTaskRunnerConfig
} from "./Configs";
import {
	AgentThreadTaskRunnerNode,
	BaseTaskRunnerNode,
	ImageToTextTaskRunnerNode,
	LoadFromDatabaseTaskRunnerNode,
	PageOrganizerTaskRunnerNode,
	RunWorkflowTaskRunnerNode,
	SaveToDatabaseTaskRunnerNode,
	SplitByFileTaskRunnerNode,
	WorkflowInputTaskRunnerNode
} from "./Nodes";

export const TaskRunners = {
	resolve(name) {
		return TaskRunners[name];
	},
	"AI Agent": {
		lottie: AgentThreadRunnerLottie,
		node: AgentThreadTaskRunnerNode,
		config: AgentThreadTaskRunnerConfig
	},
	"Base": {
		lottie: null,
		node: BaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Image To Text Transcoder": {
		lottie: ImageToTextLottie,
		node: ImageToTextTaskRunnerNode,
		config: ImageToTextTranscoderTaskRunnerConfig
	},
	"Load From Database": {
		lottie: LoadFromDbLottie,
		node: LoadFromDatabaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Pages Organizer": {
		lottie: PageOrganizerLottie,
		node: PageOrganizerTaskRunnerNode,
		config: PagesOrganizerTaskRunnerConfig
	},
	"Run Workflow": {
		lottie: RunWorkflowLottie,
		node: RunWorkflowTaskRunnerNode,
		config: RunWorkflowTaskRunnerConfig
	},
	"Save To Database": {
		lottie: SaveToDbLottie,
		node: SaveToDatabaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Split By File": {
		lottie: SplitByFileLottie,
		node: SplitByFileTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Split Artifacts By Json Content": {
		lottie: SplitByFileLottie,
		node: SplitByFileTaskRunnerNode,
		config: SplitArtifactsByJsonContentTaskRunnerConfig
	},
	"Workflow Input": {
		lottie: WorkflowInputLottie,
		node: WorkflowInputTaskRunnerNode,
		config: BaseTaskRunnerConfig
	}
};
