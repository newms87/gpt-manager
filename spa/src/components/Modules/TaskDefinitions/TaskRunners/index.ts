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
	ClassifierTaskRunnerConfig,
	ImageToTextTranscoderTaskRunnerConfig,
	MergeArtifactsTaskRunnerConfig,
	PagesOrganizerTaskRunnerConfig,
	RunWorkflowTaskRunnerConfig,
	SequentialCategoryMatcherTaskRunnerConfig,
	SplitArtifactsByJsonContentTaskRunnerConfig
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
	"Sequential Category Matcher": {
		name: "Sequential Category Matcher",
		description: "Matches categories for artifacts with missing categories based on previous or subsequent artifacts sequentially",
		lottie: CategorizeArtifactsLottie,
		config: SequentialCategoryMatcherTaskRunnerConfig
	},
	"Classifier": {
		name: "Classifier",
		description: "Classify artifacts based on their content.",
		lottie: CategorizeArtifactsLottie,
		config: ClassifierTaskRunnerConfig
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
	"Split By File": {
		name: "Split By File",
		description: "Split artifact files by individual transcodes of the file.",
		lottie: SplitByFileLottie,
		config: BaseTaskRunnerConfig
	},
	"Split Artifacts By Json Content": {
		name: "Split Artifacts By Json Content",
		description: "Split artifacts by keys in their JSON content.",
		lottie: SplitByFileLottie,
		config: SplitArtifactsByJsonContentTaskRunnerConfig
	},
	"Workflow Input": {
		name: "Workflow Input",
		description: "Allows entering files, text or JSON content as input to the workflow.",
		lottie: WorkflowInputLottie,
		node: { is: WorkflowInputTaskRunnerNode },
		config: BaseTaskRunnerConfig
	}
};
