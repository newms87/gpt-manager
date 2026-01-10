<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<QSeparator class="bg-slate-400 my-4" />

		<div class="flex flex-col gap-6">
			<div>
				<MarkdownEditor
					:model-value="taskDefinition.task_runner_config?.task_description || ''"
					label="Task Description"
					:max-length="2000"
					editor-class="min-h-[120px]"
					@update:model-value="updateTaskDescription"
				/>
				<div class="text-xs text-slate-400 mt-1">
					Provide a clear description of the task you want Claude to implement with code.
				</div>
			</div>

			<div v-if="hasGeneratedCode" class="border border-slate-600 rounded-lg p-4">
				<div class="flex items-center justify-between mb-3">
					<h4 class="text-sm font-semibold text-slate-300">Generated Code</h4>
					<div class="flex items-center gap-2">
						<div class="text-xs text-slate-400">
							Generated: {{ formatDate(taskDefinition.task_runner_config?.code_generated_at) }}
						</div>
						<QBtn
							size="sm"
							color="orange"
							:loading="isRegenerating"
							@click="regenerateCode"
						>
							Regenerate
						</QBtn>
					</div>
				</div>

				<CodeViewer
					:model-value="taskDefinition.task_runner_config?.generated_code"
					format="ts"
					editor-class="min-h-[300px] font-mono text-xs"
				/>
			</div>

			<div v-if="!hasGeneratedCode" class="bg-slate-700 border border-slate-600 rounded-lg p-4">
				<div class="flex items-center justify-between mb-2">
					<div class="flex items-center gap-2">
						<FaSolidCode class="w-4 h-4 text-blue-400" />
						<span class="text-sm font-medium text-slate-300">Code Generation</span>
					</div>
					<QBtn
						size="sm"
						color="primary"
						:loading="isGenerating"
						:disable="!taskDefinition.task_runner_config?.task_description?.trim()"
						@click="generateCodeNow"
					>
						<FaSolidPlay class="w-3 h-3 mr-1" />
						Generate Now
					</QBtn>
				</div>
				<div class="text-xs text-slate-400 mb-3">
					Code will be generated automatically when you first run this task.
					You can also generate it now to preview and customize the code.
				</div>
			</div>

			<!-- Real-time generation progress -->
			<div v-if="isGenerating" class="bg-slate-800 border border-blue-500 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-3">
					<div class="w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
					<span class="text-sm font-medium text-slate-300">Generating Code...</span>
				</div>
				
				<div class="mb-3">
					<div class="flex items-center justify-between text-xs text-slate-400 mb-1">
						<span>{{ generationProgress.message }}</span>
						<span>{{ generationProgress.progress }}%</span>
					</div>
					<div class="w-full bg-slate-600 rounded-full h-2">
						<div 
							class="bg-blue-500 h-2 rounded-full transition-all duration-300" 
							:style="`width: ${generationProgress.progress}%`"
						></div>
					</div>
				</div>

				<div v-if="streamingCode" class="border border-slate-600 rounded bg-slate-900 p-2">
					<div class="text-xs text-slate-400 mb-2">Generated Code (Live):</div>
					<CodeViewer
						:model-value="streamingCode"
						format="ts"
						editor-class="min-h-[200px] font-mono text-xs"
					/>
				</div>
			</div>

			<div class="bg-slate-700 border border-slate-600 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-2">
					<FaSolidCircleInfo class="w-4 h-4 text-blue-400" />
					<span class="text-sm font-medium text-slate-300">How it works</span>
				</div>
				<ul class="text-xs text-slate-400 space-y-1">
					<li>• First run: Claude generates PHP code based on your task description</li>
					<li>• Subsequent runs: The stored code is executed directly</li>
					<li>• Generated code has access to input artifacts and can create output artifacts</li>
					<li>• Code runs in a secure, sandboxed environment with timeout protection</li>
				</ul>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { CodeViewer } from "quasar-ui-danx";
import { usePusher } from "@/helpers/pusher";
import { TaskDefinition } from "@/types";
import { FaSolidCircleInfo, FaSolidCode, FaSolidPlay } from "danx-icon";
import { fDate, request } from "quasar-ui-danx";
import { apiUrls } from "@/api";
import { computed, onUnmounted, ref } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig.vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const isRegenerating = ref(false);
const isGenerating = ref(false);
const streamingCode = ref('');
const generationProgress = ref({
	message: '',
	progress: 0
});

const hasGeneratedCode = computed(() => {
	return !!(props.taskDefinition.task_runner_config?.generated_code);
});

function updateConfig() {
	// Initialize task_runner_config if it doesn't exist
	if (!props.taskDefinition.task_runner_config) {
		props.taskDefinition.task_runner_config = {};
	}
}

function updateTaskDescription(value: string) {
	updateConfig();
	props.taskDefinition.task_runner_config.task_description = value;
}

function formatDate(dateString?: string) {
	if (!dateString) return "Unknown";
	return fDate(dateString, "M/d/yyyy h:mm A");
}

async function regenerateCode() {
	if (!props.taskDefinition.task_runner_config?.task_description) {
		return;
	}

	isRegenerating.value = true;

	try {
		updateConfig();
		// Clear the existing generated code to force regeneration
		props.taskDefinition.task_runner_config.generated_code = null;
		props.taskDefinition.task_runner_config.code_generated_at = null;

		// The next task run will regenerate the code
	} finally {
		isRegenerating.value = false;
	}
}

// Set up Pusher for WebSocket events
const pusher = usePusher();
let eventListeners: (() => void)[] = [];

async function generateCodeNow() {
	const taskDescription = props.taskDefinition.task_runner_config?.task_description?.trim();
	if (!taskDescription) {
		return;
	}

	isGenerating.value = true;
	streamingCode.value = '';
	generationProgress.value = { message: 'Starting...', progress: 0 };

	try {
		// Set up WebSocket event listeners for this generation session
		setupCodeGenerationListeners();

		// Start the code generation process
		const response = await request.post(apiUrls.tasks.generateClaudeCode({ id: props.taskDefinition.id }), {
			task_description: taskDescription
		});

		if (!response.success) {
			throw new Error('Failed to start code generation');
		}

		generationProgress.value = { message: 'Code generation started...', progress: 5 };

	} catch (error) {
		console.error('Code generation error:', error);
		generationProgress.value = {
			message: 'Failed to start code generation',
			progress: 0
		};
		
		setTimeout(() => {
			isGenerating.value = false;
			streamingCode.value = '';
			cleanupEventListeners();
		}, 3000);
	}
}

function setupCodeGenerationListeners() {
	if (!pusher) return;

	// Listen for started event
	pusher.onEvent('ClaudeCodeGeneration', 'started', (data) => {
		if (data.task_definition_id === props.taskDefinition.id) {
			generationProgress.value = {
				message: data.message || 'Code generation started',
				progress: data.progress || 0
			};
		}
	});

	// Listen for progress events
	pusher.onEvent('ClaudeCodeGeneration', 'progress', (data) => {
		if (data.task_definition_id === props.taskDefinition.id) {
			generationProgress.value = {
				message: data.message || 'Processing...',
				progress: data.progress || 0
			};
		}
	});

	// Listen for code chunks
	pusher.onEvent('ClaudeCodeGeneration', 'code_chunk', (data) => {
		if (data.task_definition_id === props.taskDefinition.id) {
			streamingCode.value = data.total_code || (streamingCode.value + data.chunk);
			generationProgress.value = {
				message: 'Generating code...',
				progress: data.progress || 50
			};
		}
	});

	// Listen for completion
	pusher.onEvent('ClaudeCodeGeneration', 'completed', (data) => {
		if (data.task_definition_id === props.taskDefinition.id) {
			streamingCode.value = data.code || streamingCode.value;
			generationProgress.value = {
				message: data.message || 'Code generated successfully!',
				progress: 100
			};
			
			// Update the task definition with the generated code
			updateConfig();
			props.taskDefinition.task_runner_config.generated_code = data.code;
			props.taskDefinition.task_runner_config.code_generated_at = new Date().toISOString();
			
			setTimeout(() => {
				isGenerating.value = false;
				streamingCode.value = '';
				cleanupEventListeners();
			}, 2000);
		}
	});

	// Listen for errors
	pusher.onEvent('ClaudeCodeGeneration', 'error', (data) => {
		if (data.task_definition_id === props.taskDefinition.id) {
			generationProgress.value = {
				message: data.error || 'Code generation failed',
				progress: 0
			};
			
			setTimeout(() => {
				isGenerating.value = false;
				streamingCode.value = '';
				cleanupEventListeners();
			}, 3000);
		}
	});
}

function cleanupEventListeners() {
	eventListeners.forEach(cleanup => cleanup());
	eventListeners = [];
}

// Cleanup when component is unmounted
onUnmounted(() => {
	cleanupEventListeners();
});
</script>
