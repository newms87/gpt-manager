<template>
	<UiMainLayout>
		<template #header>
			<div class="px-6 py-4">
				<div class="flex items-center justify-between">
					<div class="flex items-center space-x-4">
						<ActionButton
							type="back"
							size="sm"
							label="Back"
							@click="router.back()"
						/>

						<div>
							<h1 class="text-2xl font-bold text-slate-800">
								{{ demand?.title || "Loading..." }}
							</h1>
							<div v-if="demand?.status" class="flex items-center space-x-4 mt-1">
								<UiStatusBadge :status="demand.status" />
								<span v-if="demand.created_at" class="text-sm text-slate-500">
                  Created {{ formatDate(demand.created_at) }}
                </span>
							</div>
						</div>
					</div>

					<div v-if="demand" class="flex items-center space-x-3">
						<ActionButton
							v-if="demand.status === DEMAND_STATUS.DRAFT"
							:type="editMode ? 'cancel' : 'edit'"
							:label="editMode ? 'Cancel Edit' : 'Edit'"
							@click="editMode = !editMode"
						/>

						<ActionButton
							v-if="demand.status === DEMAND_STATUS.DRAFT && demand.can_be_submitted"
							type="save"
							:loading="submitting"
							label="Submit Demand"
							@click="handleSubmit"
						/>

						<ActionButton
							v-if="demand.can_extract_data"
							type="play"
							:loading="extractingData || demand.is_extract_data_running"
							label="Extract Data"
							@click="handleExtractData"
						/>

						<ActionButton
							v-if="demand.can_write_demand"
							type="play"
							:loading="writingDemand || demand.is_write_demand_running"
							label="Write Demand"
							@click="handleWriteDemand"
						/>
					</div>
				</div>
			</div>
		</template>

		<!-- Loading State -->
		<div v-if="isLoading" class="flex items-center justify-center py-12">
			<UiLoadingSpinner size="lg" class="text-blue-500" />
			<span class="ml-3 text-slate-600">Loading demand details...</span>
		</div>

		<!-- Error State -->
		<div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
			<FaSolidExclamation class="w-5 h-5 inline mr-2" />
			{{ error }}
		</div>

		<!-- Main Content -->
		<div v-else-if="demand" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			<!-- Main Content -->
			<div class="lg:col-span-2 space-y-6">
				<!-- Demand Details -->
				<UiCard>
					<template #header>
						<h3 class="text-lg font-semibold text-slate-800">
							Demand Details
						</h3>
					</template>

					<div v-if="editMode" class="space-y-4">
						<DemandForm
							mode="edit"
							:initial-data="demand"
							@submit="handleUpdate"
							@cancel="editMode = false"
						/>
					</div>

					<div v-else class="space-y-4">
						<div>
							<label class="text-sm font-medium text-slate-700">Title</label>
							<p class="mt-1 text-slate-800">{{ demand.title }}</p>
						</div>

						<div v-if="demand.description">
							<label class="text-sm font-medium text-slate-700">Description</label>
							<p class="mt-1 text-slate-800 whitespace-pre-wrap">{{ demand.description }}</p>
						</div>

						<div class="grid grid-cols-2 gap-4">
							<div>
								<label class="text-sm font-medium text-slate-700">Status</label>
								<div class="mt-1">
									<UiStatusBadge :status="demand.status" />
								</div>
							</div>

							<div>
								<label class="text-sm font-medium text-slate-700">Progress</label>
								<div class="mt-1">
									<UiProgressBar
										:value="progressPercentage"
										:color="progressColor"
										size="sm"
										:animated="demand.status === DEMAND_STATUS.PROCESSING"
									/>
								</div>
							</div>
						</div>
					</div>
				</UiCard>

				<!-- Files Section -->
				<UiCard>
					<template #header>
						<h3 class="text-lg font-semibold text-slate-800">
							Documents
						</h3>
					</template>

					<MultiFileField
						v-model="demandFiles"
						:readonly="demand.status !== DEMAND_STATUS.DRAFT"
						:disabled="demand.status !== DEMAND_STATUS.DRAFT"
						:width="70"
						:height="60"
						add-icon-class="w-5"
						show-transcodes
						file-preview-class="rounded-lg"
						file-preview-btn-size="xs"
						@update:model-value="handleFilesUpdate"
					/>
				</UiCard>

				<!-- Workflow Error Display -->
				<UiCard v-if="workflowError" class="border-red-200 bg-red-50">
					<div class="flex items-start space-x-3">
						<FaSolidExclamation class="w-5 h-5 text-red-600 mt-0.5" />
						<div>
							<h4 class="font-medium text-red-800">Workflow Error</h4>
							<p class="text-red-700 mt-1">{{ workflowError }}</p>
						</div>
					</div>
				</UiCard>
			</div>

			<!-- Sidebar -->
			<div class="space-y-6">
				<!-- Status Timeline -->
				<UiCard>
					<template #header>
						<h3 class="text-lg font-semibold text-slate-800">
							Status Timeline
						</h3>
					</template>

					<div class="space-y-3">
						<div
							v-for="status in statusTimeline"
							:key="status.status"
							class="flex items-center space-x-3"
							:class="{ 'opacity-50': !status.completed }"
						>
							<div
								class="w-8 h-8 rounded-full flex items-center justify-center"
								:class="status.completed ? status.bgColor : 'bg-slate-200'"
							>
								<component
									:is="status.icon"
									class="w-4 h-4"
									:class="status.completed ? 'text-white' : 'text-slate-400'"
								/>
							</div>

							<div class="flex-1">
								<p class="font-medium text-slate-800">{{ status.label }}</p>
								<p v-if="status.date" class="text-sm text-slate-500">
									{{ formatDate(status.date) }}
								</p>
							</div>
						</div>
					</div>
				</UiCard>

				<!-- Quick Actions -->
				<UiCard>
					<template #header>
						<h3 class="text-lg font-semibold text-slate-800">
							Quick Actions
						</h3>
					</template>

					<div class="space-y-2">
						<ActionButton
							v-if="demand.status === DEMAND_STATUS.DRAFT"
							type="edit"
							class="w-full justify-start"
							label="Edit Details"
							@click="editMode = true"
						/>

						<ActionButton
							v-if="demand.can_extract_data"
							type="play"
							class="w-full justify-start"
							:loading="extractingData || demand.is_extract_data_running"
							label="Extract Data"
							@click="handleExtractData"
						/>

						<ActionButton
							v-if="demand.can_write_demand"
							type="play"
							class="w-full justify-start"
							:loading="writingDemand || demand.is_write_demand_running"
							label="Write Demand"
							@click="handleWriteDemand"
						/>

						<ActionButton
							type="copy"
							class="w-full justify-start"
							label="Duplicate Demand"
							@click="duplicateDemand"
						/>

						<ActionButton
							type="trash"
							class="w-full justify-start"
							label="Delete Demand"
							@click="deleteDemand"
						/>
					</div>
				</UiCard>
			</div>
		</div>
	</UiMainLayout>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidClock, FaSolidExclamation, FaSolidSpinner } from "danx-icon";
import { ActionButton, MultiFileField } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { UiCard, UiLoadingSpinner, UiMainLayout, UiProgressBar, UiStatusBadge } from "../../shared";
import type { UiDemand } from "../../shared/types";
import { DemandForm } from "../components";
import { useDemands } from "../composables";
import { DEMAND_STATUS, demandRoutes, getDemandProgressPercentage, getDemandStatusColor } from "../config";

const route = useRoute();
const router = useRouter();

const {
	updateDemand,
	submitDemand,
	extractData,
	writeDemand,
	deleteDemand: deleteDemandAction
} = useDemands();

const demand = ref<UiDemand | null>(null);
const demandFiles = ref([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const editMode = ref(false);
const submitting = ref(false);
const extractingData = ref(false);
const writingDemand = ref(false);
const workflowError = ref<string | null>(null);

const demandId = computed(() => {
	const id = route.params.id;
	return typeof id === "string" ? parseInt(id, 10) : null;
});

const progressColor = computed(() => {
	if (!demand.value) return "blue";
	return getDemandStatusColor(demand.value.status);
});

const progressPercentage = computed(() => {
	if (!demand.value) return 0;
	return getDemandProgressPercentage(demand.value.status);
});

const statusTimeline = computed(() => {
	if (!demand.value) return [];

	return [
		{
			status: "draft",
			label: "Created",
			icon: FaSolidClock,
			bgColor: "bg-slate-500",
			completed: true,
			date: demand.value.created_at
		},
		{
			status: "ready",
			label: "Submitted",
			icon: FaSolidCheck,
			bgColor: "bg-blue-500",
			completed: demand.value.submitted_at !== null,
			date: demand.value.submitted_at
		},
		{
			status: "processing",
			label: "Processing",
			icon: FaSolidSpinner,
			bgColor: "bg-amber-500",
			completed: demand.value.status === DEMAND_STATUS.PROCESSING || demand.value.status === DEMAND_STATUS.COMPLETED,
			date: demand.value.status === DEMAND_STATUS.PROCESSING ? demand.value.updated_at : null
		},
		{
			status: "completed",
			label: "Completed",
			icon: FaSolidCheck,
			bgColor: "bg-green-500",
			completed: demand.value.status === DEMAND_STATUS.COMPLETED,
			date: demand.value.completed_at
		}
	];
});

const formatDate = (dateString: string) => {
	return new Date(dateString).toLocaleDateString("en-US", {
		year: "numeric",
		month: "long",
		day: "numeric",
		hour: "numeric",
		minute: "2-digit"
	});
};

const loadDemand = async () => {
	if (!demandId.value) return;

	try {
		isLoading.value = true;
		error.value = null;
		demand.value = await demandRoutes.details({ id: demandId.value });
	} catch (err: any) {
		error.value = err.message || "Failed to load demand";
	} finally {
		isLoading.value = false;
	}
};

const handleUpdate = async (data: { title: string; description: string; files?: any[] }) => {
	if (!demand.value) return;

	try {
		const updatedDemand = await updateDemand(demand.value.id, data);
		demand.value = updatedDemand;
		demandFiles.value = updatedDemand.files || [];
		editMode.value = false;
	} catch (err: any) {
		error.value = err.message || "Failed to update demand";
	}
};

const handleSubmit = async () => {
	if (!demand.value) return;

	try {
		submitting.value = true;
		const updatedDemand = await submitDemand(demand.value.id);
		demand.value = updatedDemand;
	} catch (err: any) {
		error.value = err.message || "Failed to submit demand";
	} finally {
		submitting.value = false;
	}
};

const handleExtractData = async () => {
	if (!demand.value) return;

	try {
		extractingData.value = true;
		workflowError.value = null;
		const updatedDemand = await extractData(demand.value.id);
		demand.value = updatedDemand;
	} catch (err: any) {
		workflowError.value = err.message || "Failed to extract data";
	} finally {
		extractingData.value = false;
	}
};

const handleWriteDemand = async () => {
	if (!demand.value) return;

	try {
		writingDemand.value = true;
		workflowError.value = null;
		const updatedDemand = await writeDemand(demand.value.id);
		demand.value = updatedDemand;
	} catch (err: any) {
		workflowError.value = err.message || "Failed to write demand";
	} finally {
		writingDemand.value = false;
	}
};

const handleFilesUpdate = async (files: any[]) => {
	if (!demand.value) return;

	try {
		const updatedDemand = await updateDemand(demand.value.id, { files });
		demand.value = updatedDemand;
		demandFiles.value = files;
	} catch (err: any) {
		error.value = err.message || "Failed to update files";
	}
};

const duplicateDemand = () => {
	// TODO: Implement duplicate functionality
	console.log("Duplicate demand");
};

const deleteDemand = async () => {
	if (!demand.value) return;

	if (confirm("Are you sure you want to delete this demand? This action cannot be undone.")) {
		try {
			await deleteDemandAction(demand.value.id);
			router.push("/ui/demands");
		} catch (err: any) {
			error.value = err.message || "Failed to delete demand";
		}
	}
};

// Watch for route changes and load demand
watch(demandId, loadDemand, { immediate: true });

// Sync demandFiles with demand.files
watch(() => demand.value?.files, (files) => {
	demandFiles.value = files || [];
}, { immediate: true });
</script>
