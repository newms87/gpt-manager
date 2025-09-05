<template>
	<ConfirmDialog
		title="Select a Template for Writing Demand"
		content-class="w-[90vw] max-w-5xl"
		confirm-text="Write Demand"
		cancel-text="Cancel"
		:disabled="!selectedTemplate"
		@confirm="handleConfirm"
		@close="handleCancel"
	>
		<div class="space-y-6">
			<!-- Loading State -->
			<div v-if="isLoading" class="flex flex-col items-center justify-center py-16">
				<QSpinner size="lg" color="blue-500" />
				<div class="text-gray-600 mt-4 font-medium">Loading templates...</div>
			</div>

			<!-- Empty State -->
			<div
				v-else-if="activeTemplates.length === 0"
				class="flex flex-col items-center justify-center py-16 bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl shadow-lg"
			>
				<FaSolidFile class="w-16 text-blue-500 mb-4" />
				<div class="text-xl font-semibold text-gray-900 mb-2">No active templates available</div>
				<div class="text-gray-600 mb-6">Create your first template to get started</div>
				<ActionButton
					type="create"
					label="Create Template"
					color="blue"
					@click="goToCreateTemplate"
				/>
			</div>

			<!-- Templates Grid -->
			<div v-else class="space-y-6">
				<!-- Document Templates Section -->
				<div>
					<h3 class="text-lg font-semibold text-gray-900 mb-4">Document Templates</h3>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<div
							v-for="(template, index) in activeTemplates"
							:key="template.id"
							:class="[
                'rounded-2xl p-6 cursor-pointer transition-all duration-300 scale-[.95] hover:scale-[1] hover:shadow-xl text-white',
                'shadow-lg hover:shadow-2xl transform',
                selectedTemplate?.id === template.id
                  ? getSelectedCardClasses(index)
                  : getCardClasses(index)
              ]"
							@click="selectedTemplate = template"
						>
							<!-- Template Header -->
							<div class="flex items-start justify-between mb-4">
								<h4 class="font-bold text-xl leading-tight">{{ template.name }}</h4>
								<div v-if="selectedTemplate?.id === template.id" class="flex-shrink-0 ml-2">
									<FaSolidCheck class="w-6 h-6 text-white bg-white/20 rounded-full p-1" />
								</div>
							</div>

							<!-- Template Description -->
							<div v-if="template.description" class="mb-4">
								<p class="text-white/90 leading-relaxed">{{ template.description }}</p>
							</div>

							<!-- Template Footer -->
							<div class="flex items-center justify-between pt-4">
								<a
									v-if="template.template_url"
									:href="template.template_url"
									target="_blank"
									class="text-white/90 hover:text-white transition-colors flex items-center space-x-1 text-sm font-medium bg-white/10 px-3 py-1 rounded-full backdrop-blur-sm hover:bg-white/20"
									@click.stop
								>
									<FaSolidLink class="w-3 h-3" />
									<span>Preview</span>
								</a>
							</div>
						</div>
					</div>
				</div>

				<!-- Instruction Templates Section -->
				<div>
					<div class="flex items-center justify-between mb-4">
						<h3 class="text-lg font-semibold text-gray-900">Instruction Templates</h3>
						<ActionButton
							type="create"
							label="Manage Templates"
							size="sm"
							color="blue"
							@click="goToInstructionTemplates"
						/>
					</div>
					
					<div v-if="isLoadingInstructionTemplates" class="flex justify-center py-8">
						<QSpinner size="md" color="blue" />
					</div>

					<div v-else-if="instructionTemplates.length === 0" class="bg-slate-50 border border-slate-200 rounded-lg p-6 text-center">
						<FaSolidFile class="w-8 h-8 text-slate-400 mx-auto mb-2" />
						<p class="text-slate-600 text-sm mb-3">No global instruction templates available</p>
						<ActionButton
							type="create"
							label="Create First Template"
							size="sm"
							color="sky"
							@click="goToInstructionTemplates"
						/>
					</div>

					<div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div
							v-for="instructionTemplate in instructionTemplates"
							:key="instructionTemplate.id"
							:class="[
								'bg-slate-50 border border-slate-200 rounded-lg p-4 cursor-pointer transition-all duration-200',
								'hover:border-slate-300 hover:shadow-md',
								selectedInstructionTemplate?.id === instructionTemplate.id
									? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200'
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
									<FaSolidCheck class="w-4 h-4 text-blue-600" />
								</div>
							</div>
						</div>
					</div>

					<div v-if="instructionTemplates.length > 0" class="text-xs text-slate-500 mt-2">
						Select an instruction template (optional) to provide specific guidance for writing this demand.
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
						placeholder="Enter any specific instructions for writing this demand..."
						class="w-full"
					/>
				</div>
			</div>
		</div>
	</ConfirmDialog>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidFile, FaSolidLink } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog, TextField } from "quasar-ui-danx";
import { onMounted, ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useDemandTemplates } from "../composables/useDemandTemplates";
import type { DemandTemplate } from "../types";
import type { UiDemand } from "../../shared/types";
import type { WorkflowInput } from "@/types";
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/config";

const props = defineProps<{
	demand?: UiDemand | null;
}>();

const emit = defineEmits<{
	"confirm": [template: DemandTemplate, instructions: string, instructionTemplate?: WorkflowInput];
	"close": [];
}>();

const router = useRouter();
const { activeTemplates, isLoading, loadActiveTemplates } = useDemandTemplates();

const selectedTemplate = ref<DemandTemplate | null>(null);
const selectedInstructionTemplate = ref<WorkflowInput | null>(null);
const additionalInstructions = ref("");
const isLoadingInstructionTemplates = ref(false);
const loadedInstructionTemplates = ref<WorkflowInput[]>([]);

// Computed properties for instruction templates
const instructionTemplates = computed(() => {
	return loadedInstructionTemplates.value;
});

// Vibrant color schemes for template cards
const cardColors = [
	"bg-gradient-to-br from-blue-500 to-blue-600",
	"bg-gradient-to-br from-green-500 to-emerald-600",
	"bg-gradient-to-br from-purple-500 to-violet-600",
	"bg-gradient-to-br from-orange-500 to-amber-600",
	"bg-gradient-to-br from-pink-500 to-rose-600",
	"bg-gradient-to-br from-indigo-500 to-purple-600",
	"bg-gradient-to-br from-teal-500 to-cyan-600",
	"bg-gradient-to-br from-red-500 to-pink-600"
];

const selectedCardColors = [
	"bg-gradient-to-br from-blue-600 to-blue-700 ring-4 ring-blue-300",
	"bg-gradient-to-br from-green-600 to-emerald-700 ring-4 ring-green-300",
	"bg-gradient-to-br from-purple-600 to-violet-700 ring-4 ring-purple-300",
	"bg-gradient-to-br from-orange-600 to-amber-700 ring-4 ring-orange-300",
	"bg-gradient-to-br from-pink-600 to-rose-700 ring-4 ring-pink-300",
	"bg-gradient-to-br from-indigo-600 to-purple-700 ring-4 ring-indigo-300",
	"bg-gradient-to-br from-teal-600 to-cyan-700 ring-4 ring-teal-300",
	"bg-gradient-to-br from-red-600 to-pink-700 ring-4 ring-red-300"
];

const getCardClasses = (index: number) => {
	return cardColors[index % cardColors.length];
};

const getSelectedCardClasses = (index: number) => {
	return selectedCardColors[index % selectedCardColors.length];
};

// Load global instruction templates  
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
	await Promise.all([
		loadActiveTemplates(),
		loadInstructionTemplates()
	]);
});

const handleConfirm = () => {
	
	if (!selectedTemplate.value) {
		return;
	}
	
	emit("confirm", selectedTemplate.value, additionalInstructions.value, selectedInstructionTemplate.value || undefined);
};

const handleCancel = () => {
	emit("close");
};

const goToCreateTemplate = () => {
	emit("close");
	router.push("/ui/templates");
};

const goToInstructionTemplates = () => {
	emit("close");
	router.push("/workflow-inputs");
};
</script>
