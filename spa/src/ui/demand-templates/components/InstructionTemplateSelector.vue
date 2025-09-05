<template>
	<ConfirmDialog
		:title="currentView === 'selector' ? 'Select an Instruction Template for Writing Medical Summary' : 'Manage Instruction Templates'"
		content-class="w-[90vw] max-w-4xl"
		:confirm-text="currentView === 'selector' ? 'Write Medical Summary' : 'Done'"
		cancel-text="Cancel"
		:disabled="currentView === 'selector' && false"
		@confirm="handleConfirm"
		@close="handleCancel"
	>
		<!-- Navigation Bar for Manager View -->
		<div v-if="currentView === 'manager'" class="mb-4 pb-4 border-b border-gray-200">
			<ActionButton
				type="arrow-left"
				label="Back to Template Selection"
				size="sm"
				color="gray"
				@click="navigateToSelector"
			/>
		</div>

		<!-- Selector View -->
		<div v-if="currentView === 'selector'" class="space-y-6">
			<!-- Loading State -->
			<div v-if="isLoadingInstructionTemplates" class="flex flex-col items-center justify-center py-16">
				<QSpinner size="lg" color="green-500" />
				<div class="text-gray-600 mt-4 font-medium">Loading instruction templates...</div>
			</div>

			<!-- Empty State -->
			<div
				v-else-if="instructionTemplates.length === 0"
				class="flex flex-col items-center justify-center py-16 bg-gradient-to-br from-green-50 to-teal-50 rounded-2xl shadow-lg"
			>
				<FaSolidFile class="w-16 text-green-500 mb-4" />
				<div class="text-xl font-semibold text-gray-900 mb-2">No instruction templates available</div>
				<div class="text-gray-600 mb-6">Create your first instruction template to get started</div>
				<ActionButton
					type="create"
					label="Create Template"
					color="green"
					@click="navigateToManager"
				/>
			</div>

			<!-- Templates Grid -->
			<div v-else class="space-y-6">
				<!-- Instruction Templates Section -->
				<div>
					<div class="flex items-center justify-between mb-4">
						<h3 class="text-lg font-semibold text-gray-900">Instruction Templates</h3>
						<ActionButton
							type="create"
							label="Manage Templates"
							size="sm"
							color="green"
							@click="navigateToManager"
						/>
					</div>

					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div
							v-for="instructionTemplate in instructionTemplates"
							:key="instructionTemplate.id"
							:class="[
								'bg-slate-50 border border-slate-200 rounded-lg p-4 cursor-pointer transition-all duration-200',
								'hover:border-slate-300 hover:shadow-md',
								selectedInstructionTemplate?.id === instructionTemplate.id
									? 'border-green-500 bg-green-50 ring-2 ring-green-200'
									: ''
							]"
							@click="selectedInstructionTemplate = selectedInstructionTemplate?.id === instructionTemplate.id ? null : instructionTemplate"
						>
							<div class="flex items-start justify-between">
								<div class="flex-1">
									<h4 class="font-medium text-slate-900 text-sm mb-1">{{ instructionTemplate.name }}</h4>
									<p v-if="instructionTemplate.description" class="text-slate-600 text-xs line-clamp-2">
										{{ instructionTemplate.description }}
									</p>
								</div>
								<div v-if="selectedInstructionTemplate?.id === instructionTemplate.id" class="flex-shrink-0 ml-2">
									<FaSolidCheck class="w-4 h-4 text-green-600" />
								</div>
							</div>
						</div>
					</div>

					<div v-if="instructionTemplates.length > 0" class="text-xs text-slate-500 mt-2">
						Select an instruction template (optional) to provide specific guidance for writing the medical summary.
					</div>
				</div>

				<!-- Additional Instructions Section -->
				<div class="bg-white rounded-2xl shadow-md p-6 border border-gray-100">
					<label class="block text-lg font-semibold text-gray-900 mb-4">
						Additional Instructions (Optional)
					</label>
					<TextField
						v-model="additionalInstructions"
						type="textarea"
						:rows="10"
						placeholder="Enter any specific instructions for writing the medical summary..."
						class="w-full"
					/>
				</div>
			</div>
		</div>

		<!-- Manager View -->
		<div v-if="currentView === 'manager'">
			<InstructionTemplateManager 
				ref="templateManagerRef"
				@templates-updated="handleTemplatesUpdated"
			/>
		</div>
	</ConfirmDialog>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidFile } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog, TextField } from "quasar-ui-danx";
import { onMounted, ref, computed } from "vue";
import type { UiDemand } from "../../shared/types";
import type { WorkflowInput } from "@/types";
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/config";
import InstructionTemplateManager from "./InstructionTemplateManager.vue";

const props = defineProps<{
	demand?: UiDemand | null;
}>();

const emit = defineEmits<{
	"confirm": [instructionTemplate: WorkflowInput | null, instructions: string];
	"close": [];
}>();

const selectedInstructionTemplate = ref<WorkflowInput | null>(null);
const additionalInstructions = ref("");
const isLoadingInstructionTemplates = ref(false);
const loadedInstructionTemplates = ref<WorkflowInput[]>([]);
const currentView = ref<'selector' | 'manager'>('selector');
const templateManagerRef = ref<InstanceType<typeof InstructionTemplateManager> | null>(null);

// Computed properties for instruction templates
const instructionTemplates = computed(() => {
	return loadedInstructionTemplates.value;
});

// Load global instruction templates for medical summary
const loadInstructionTemplates = async () => {
	try {
		isLoadingInstructionTemplates.value = true;
		const result = await dxWorkflowInput.routes.list({
			filter: {
				"associations.associable_type": "App\\Models\\Demand\\UiDemand",
				"associations.category": "write_demand_instructions"
			}
		});
		
		loadedInstructionTemplates.value = result.data || [];
	} catch (error) {
		console.error("Error loading instruction templates:", error);
		loadedInstructionTemplates.value = [];
	} finally {
		isLoadingInstructionTemplates.value = false;
	}
};

// Load templates when component mounts
onMounted(async () => {
	await loadInstructionTemplates();
});

const handleConfirm = () => {
	if (currentView.value === 'manager') {
		// When done managing templates, go back to selector
		navigateToSelector();
	} else {
		// When in selector view, emit confirm with selected template
		emit("confirm", selectedInstructionTemplate.value, additionalInstructions.value);
	}
};

const handleCancel = () => {
	emit("close");
};

const navigateToManager = () => {
	currentView.value = 'manager';
};

const navigateToSelector = () => {
	currentView.value = 'selector';
	// Reload templates when returning to selector
	loadInstructionTemplates();
};

const handleTemplatesUpdated = () => {
	// This will be called when templates are updated in the manager
	// We'll reload them when navigating back to the selector
};
</script>