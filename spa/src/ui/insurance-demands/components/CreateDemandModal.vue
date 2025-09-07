<template>
    <ConfirmDialog
        class="ui-mode"
        title="Create New Demand"
        content-class="w-[600px]"
        confirm-text="Create Demand"
        cancel-text="Cancel"
        :is-saving="creating"
        :disabled="!isFormValid || isUploading"
        @confirm="handleSubmit"
        @close="handleClose"
    >
        <div class="space-y-6">
            <!-- Title Field -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Title *
                </label>
                <UiInput
                    v-model="formData.title"
                    placeholder="Enter a descriptive title for your demand..."
                    required
                    :error="errors.title"
                    class="w-full"
                />
            </div>

            <!-- Description Field -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Description
                </label>
                <UiTextarea
                    v-model="formData.description"
                    placeholder="Provide additional details about your demand..."
                    :rows="4"
                    :error="errors.description"
                    class="w-full"
                />
            </div>

            <!-- File Upload -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Documents
                </label>
                <MultiFileField
                    v-model="inputFiles"
                    placeholder="Upload supporting documents..."
                    :error="errors.input_files"
                    class="w-full"
                />
                <p class="text-xs text-slate-500 mt-1">
                    Supported formats: PDF, DOC, DOCX, JPG, PNG
                </p>
            </div>

            <!-- Upload Status Warning -->
            <div v-if="isUploading" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <div class="flex items-center">
                    <FaSolidSpinner class="w-4 h-4 text-amber-600 animate-spin mr-2" />
                    <div class="text-sm text-amber-700">
                        Files are still uploading... Please wait before saving.
                    </div>
                </div>
            </div>
        </div>
    </ConfirmDialog>
</template>

<script setup lang="ts">
import { FaSolidSpinner } from "danx-icon";
import { ConfirmDialog, FlashMessages, MultiFileField, StoredFile } from "quasar-ui-danx";
import { computed, reactive, ref } from "vue";
import { UiInput, UiTextarea } from "../../shared/components";
import { useDemands } from "../composables";
import { DEMAND_STATUS } from "../config";

const emit = defineEmits<{
    close: [];
}>();

const { createDemand, demands } = useDemands();
const creating = ref(false);

const inputFiles = ref<Array<StoredFile>>([]);
const formData = reactive({
    title: "",
    description: ""
});

const errors = reactive({
    title: "",
    description: "",
    input_files: ""
});

const isFormValid = computed(() => {
    return formData.title.trim().length > 0;
});

const isUploading = computed(() => inputFiles.value.some(file => !file.__type || file.progress < 100));

const validateForm = (): boolean => {
    let isValid = true;

    // Clear previous errors
    Object.keys(errors).forEach(key => {
        errors[key as keyof typeof errors] = "";
    });

    // Validate title
    if (!formData.title.trim()) {
        errors.title = "Title is required";
        isValid = false;
    } else {
        // Check for duplicate title
        const existingDemand = demands.value.find(demand =>
            demand.title.toLowerCase() === formData.title.trim().toLowerCase()
        );
        if (existingDemand) {
            errors.title = "A demand with this title already exists";
            isValid = false;
        }
    }

    return isValid;
};

const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }

    try {
        creating.value = true;

        const result = await createDemand({
            title: formData.title.trim(),
            description: formData.description.trim() || null,
            status: DEMAND_STATUS.DRAFT,
            input_files: inputFiles.value
        });

        if (result.success) {
            emit("close");
        } else {
            FlashMessages.error("Failed to create demand" + (result.message ? ": " + result.message : ""));
        }
    } catch (error: any) {
        FlashMessages.error("Failed to create demand" + (error.message ? ": " + error.message : ""));
    } finally {
        creating.value = false;
    }
};

const handleClose = () => {
    // Warn if files are uploading
    if (isUploading.value && !confirm("Files are still uploading. Are you sure you want to close? Your progress will be lost.")) {
        return;
    }
    emit("close");
};
</script>
