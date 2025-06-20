import {
	AgentThreadRunnerLottie,
	ArtifactLevelProjectionLottie,
	CategorizeArtifactsLottie,
	FilterArtifactsLottie,
	ImageToTextLottie,
	LoadCsvLottie,
	LoadFromDbLottie,
	MergeArtifactsLottie,
	PageOrganizerLottie,
	RunWorkflowLottie,
	SaveToDbLottie,
	SequentialCategoryMatcherLottie,
	SplitArtifactsLottie,
	SplitByFileLottie,
	WorkflowInputLottie
} from "@/assets/dotlottie";
import { TaskRunnerClass } from "@/types";
import {
	AgentThreadTaskRunnerConfig,
	ArtifactLevelProjectionTaskRunnerConfig,
	BaseTaskRunnerConfig,
	CategorizeArtifactsTaskRunnerConfig,
	ClassifierTaskRunnerConfig,
	ClaudeTaskRunnerConfig,
	FilterArtifactsTaskRunnerConfig,
	ImageToTextTranscoderTaskRunnerConfig,
	LoadCsvTaskRunnerConfig,
	MergeArtifactsTaskRunnerConfig,
	PagesOrganizerTaskRunnerConfig,
	RunWorkflowTaskRunnerConfig,
	SequentialCategoryMatcherTaskRunnerConfig,
	SplitArtifactsTaskRunnerConfig
} from "./Configs";
import { WorkflowInputTaskRunnerNode } from "./Nodes";

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
		config: AgentThreadTaskRunnerConfig
	},
	"Categorize Artifacts": {
		name: "Categorize Artifacts",
		description: "Categorize artifacts based on their content.",
		lottie: CategorizeArtifactsLottie,
		config: CategorizeArtifactsTaskRunnerConfig
	},
	"Classifier": {
		name: "Classifier",
		description: "Classify artifacts based on their content.",
		lottie: CategorizeArtifactsLottie,
		config: ClassifierTaskRunnerConfig
	},
	"Claude Code": {
		name: "Claude Code",
		description: "Generate and execute custom PHP code using Claude AI to perform complex tasks.",
		lottie: AgentThreadRunnerLottie,
		config: ClaudeTaskRunnerConfig
	},
	"Filter Artifacts": {
		name: "Filter Artifacts",
		description: "Filter artifacts based on their content with complex AND/OR conditions",
		lottie: FilterArtifactsLottie,
		config: FilterArtifactsTaskRunnerConfig
	},
	"Artifact Level Projection": {
		name: "Artifact Level Projection",
		description: "Project content between different artifact levels while respecting hierarchy relationships",
		lottie: ArtifactLevelProjectionLottie, 
		config: ArtifactLevelProjectionTaskRunnerConfig
	},
	"Image To Text Transcoder": {
		name: "Image To Text Transcoder",
		description: "Transcode images to text using OCR + AI in combination",
		lottie: ImageToTextLottie,
		node: {
			lottieClass: "w-[10rem]"
		},
		config: ImageToTextTranscoderTaskRunnerConfig
	},
	"Load From Database": {
		name: "Load From Database",
		description: "Load artifacts from a database.",
		lottie: LoadFromDbLottie,
		node: {
			lottieClass: "w-[10rem]"
		},
		config: BaseTaskRunnerConfig
	},
	"Merge Artifacts": {
		name: "Merge Artifacts",
		description: "Merge multiple artifacts into one.",
		lottie: MergeArtifactsLottie,
		config: MergeArtifactsTaskRunnerConfig
	},
	"Pages Organizer": {
		name: "Pages Organizer",
		description: "Organize pages from multiple artifacts into related groups",
		lottie: PageOrganizerLottie,
		node: {
			lottieClass: "w-[10rem]"
		},
		config: PagesOrganizerTaskRunnerConfig
	},
	"Run Workflow": {
		name: "Run Workflow",
		description: "Run a workflow from this workflow",
		lottie: RunWorkflowLottie,
		node: {
			lottieClass: "w-[10rem]"
		},
		config: RunWorkflowTaskRunnerConfig
	},
	"Save To Database": {
		name: "Save To Database",
		description: "Save artifacts to a database.",
		lottie: SaveToDbLottie,
		node: {
			lottieClass: "w-[14rem] mt-[-.5rem]"
		},
		config: BaseTaskRunnerConfig
	},
	"Sequential Category Matcher": {
		name: "Sequential Category Matcher",
		description: "Matches categories for artifacts with missing categories based on previous or subsequent artifacts sequentially",
		lottie: SequentialCategoryMatcherLottie,
		config: SequentialCategoryMatcherTaskRunnerConfig
	},
	"Split By File": {
		name: "Split By File",
		description: "Split artifact files by individual transcodes of the file.",
		lottie: SplitByFileLottie,
		config: BaseTaskRunnerConfig
	},
	"Split Artifacts": {
		name: "Split Artifacts",
		description: "Split artifacts by keys in their JSON content.",
		lottie: SplitArtifactsLottie,
		config: SplitArtifactsTaskRunnerConfig
	},
	"Workflow Input": {
		name: "Workflow Input",
		description: "Allows entering files, text or JSON content as input to the workflow.",
		lottie: WorkflowInputLottie,
		node: { is: WorkflowInputTaskRunnerNode },
		config: BaseTaskRunnerConfig
	},
	"Load Csv": {
		name: "Load Csv",
		description: "Process CSV files and create artifacts from rows with configurable batching.",
		lottie: LoadCsvLottie,
		node: {
			lottieClass: "w-[10rem]"
		},
		config: LoadCsvTaskRunnerConfig
	}
};
