import {
	AgentThreadRunnerLottie,
	CategorizeArtifactsLottie,
	ImageToTextLottie,
	LoadFromDbLottie,
	MergeArtifactsLottie,
	PageOrganizerLottie,
	RunWorkflowLottie,
	SaveToDbLottie,
	SplitByFileLottie,
	WorkflowInputLottie
} from "@/assets/dotlottie";
import { TaskRunnerClass } from "@/types";
import {
	AgentThreadTaskRunnerConfig,
	BaseTaskRunnerConfig,
	CategorizeArtifactsTaskRunnerConfig,
	ImageToTextTranscoderTaskRunnerConfig,
	MergeArtifactsTaskRunnerConfig,
	PagesOrganizerTaskRunnerConfig,
	RunWorkflowTaskRunnerConfig,
	SplitArtifactsByJsonContentTaskRunnerConfig
} from "./Configs";
import {
	AgentThreadTaskRunnerNode,
	BaseTaskRunnerNode,
	CategorizeArtifactsTaskRunnerNode,
	ImageToTextTaskRunnerNode,
	LoadFromDatabaseTaskRunnerNode,
	MergeArtifactsTaskRunnerNode,
	PageOrganizerTaskRunnerNode,
	RunWorkflowTaskRunnerNode,
	SaveToDatabaseTaskRunnerNode,
	SplitByFileTaskRunnerNode,
	WorkflowInputTaskRunnerNode
} from "./Nodes";

export const TaskRunnerClasses = {
	list(): TaskRunnerClass[] {
		return Object.keys(TaskRunnerClasses).filter((key) => !["Base", "list", "resolve"].includes(key)).sort().map(n => TaskRunnerClasses[n]) as TaskRunnerClass[];
	},
	resolve(name) {
		return TaskRunnerClasses[name];
	},
	"AI Agent": {
		name: "AI Agent",
		description: "Use an LLM to generate a response based on the input.",
		lottie: AgentThreadRunnerLottie,
		node: AgentThreadTaskRunnerNode,
		config: AgentThreadTaskRunnerConfig
	},
	"Base": {
		name: "Base",
		lottie: null,
		node: BaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Categorize Artifacts": {
		name: "Categorize Artifacts",
		description: "Categorize artifacts based on their content.",
		lottie: CategorizeArtifactsLottie,
		node: CategorizeArtifactsTaskRunnerNode,
		config: CategorizeArtifactsTaskRunnerConfig
	},
	"Image To Text Transcoder": {
		name: "Image To Text Transcoder",
		description: "Transcode images to text using OCR + AI in combination",
		lottie: ImageToTextLottie,
		node: ImageToTextTaskRunnerNode,
		config: ImageToTextTranscoderTaskRunnerConfig
	},
	"Load From Database": {
		name: "Load From Database",
		description: "Load artifacts from a database.",
		lottie: LoadFromDbLottie,
		node: LoadFromDatabaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Merge Artifacts": {
		name: "Merge Artifacts",
		description: "Merge multiple artifacts into one.",
		lottie: MergeArtifactsLottie,
		node: MergeArtifactsTaskRunnerNode,
		config: MergeArtifactsTaskRunnerConfig
	},
	"Pages Organizer": {
		name: "Pages Organizer",
		description: "Organize pages from multiple artifacts into related groups",
		lottie: PageOrganizerLottie,
		node: PageOrganizerTaskRunnerNode,
		config: PagesOrganizerTaskRunnerConfig
	},
	"Run Workflow": {
		name: "Run Workflow",
		description: "Run a workflow from this workflow",
		lottie: RunWorkflowLottie,
		node: RunWorkflowTaskRunnerNode,
		config: RunWorkflowTaskRunnerConfig
	},
	"Save To Database": {
		name: "Save To Database",
		description: "Save artifacts to a database.",
		lottie: SaveToDbLottie,
		node: SaveToDatabaseTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Split By File": {
		name: "Split By File",
		description: "Split artifact files by individual transcodes of the file.",
		lottie: SplitByFileLottie,
		node: SplitByFileTaskRunnerNode,
		config: BaseTaskRunnerConfig
	},
	"Split Artifacts By Json Content": {
		name: "Split Artifacts By Json Content",
		description: "Split artifacts by keys in their JSON content.",
		lottie: SplitByFileLottie,
		node: SplitByFileTaskRunnerNode,
		config: SplitArtifactsByJsonContentTaskRunnerConfig
	},
	"Workflow Input": {
		name: "Workflow Input",
		description: "Allows entering files, text or JSON content as input to the workflow.",
		lottie: WorkflowInputLottie,
		node: WorkflowInputTaskRunnerNode,
		config: BaseTaskRunnerConfig
	}
};
