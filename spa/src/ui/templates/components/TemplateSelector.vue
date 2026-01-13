<template>
    <ConfirmDialog
        class="ui-mode"
        :title="dialogTitle"
        content-class="w-[90vw] max-w-4xl"
        :confirm-text="editMode ? 'Done' : 'Write Demand Letter'"
        :cancel-text="editMode ? null : 'Cancel'"
        :disabled="!selectedTemplate && !editMode"
        @confirm="handleConfirm"
        @close="$emit('close')"
    >
        <div class="space-y-6">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex flex-col items-center justify-center py-16">
                <QSpinner size="lg" color="blue-500" />
                <div class="text-gray-600 mt-4 font-medium">Loading templates...</div>
            </div>

            <!-- Empty State (View Mode Only) -->
            <div
                v-else-if="activeTemplates.length === 0 && !editMode"
                class="flex flex-col items-center justify-center py-16 bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl shadow-lg"
            >
                <FaSolidFile class="w-16 text-blue-500 mb-4" />
                <div class="text-xl font-semibold text-gray-900 mb-2">No active templates available</div>
                <div class="text-gray-600 mb-6">Create your first template to get started</div>
                <ActionButton
                    type="create"
                    label="Create Template"
                    color="blue"
                    @click="toggleEditMode"
                />
            </div>

            <!-- Content (when templates exist or in edit mode) -->
            <div v-else class="space-y-6">
                <!-- Mode Toggle Header -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ editMode ? 'Manage Templates' : 'Document Templates' }}
                    </h3>
                    <ActionButton
                        :type="editMode ? 'view' : 'edit'"
                        :label="editMode ? 'View Mode' : 'Manage Templates'"
                        size="sm"
                        color="blue"
                        @click="toggleEditMode"
                    />
                </div>

                <!-- Manage Mode - Template Editing -->
                <TemplateManagementList
                    v-if="editMode"
                    :templates="activeTemplates"
                    :is-loading="isLoading"
                    empty-state-title="No templates yet"
                    empty-state-description="Create your first template to get started"
                    :show-create-button="true"
                />

                <!-- View Mode - Selection Grid (existing implementation) -->
                <div v-else>
                    <!-- Document Templates Section -->
                    <div>
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

                    <!-- Additional Instructions Section (only in view mode) -->
                    <div class="bg-white rounded-2xl shadow-md p-6 border border-gray-100 mt-6">
                        <label class="block text-lg font-semibold text-gray-900 mb-4">
                            Additional Instructions (Optional)
                        </label>
                        <TextField
                            v-model="additionalInstructions"
                            type="textarea"
                            :rows="10"
                            placeholder="Enter any specific instructions for writing this demand letter..."
                            class="w-full"
                        />
                    </div>
                </div>
            </div>
        </div>
    </ConfirmDialog>
</template>

<script setup lang="ts">
import { FaSolidCheck, FaSolidFile, FaSolidLink } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog, TextField } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import type { UiDemand } from "../../shared/types";
import { useTemplateDefinitions } from "../composables/useTemplateDefinitions";
import type { TemplateDefinition } from "../types";
import TemplateManagementList from "./TemplateManagementList.vue";

const emit = defineEmits(["confirm", "close"]);

const props = defineProps<{
    demand?: UiDemand | null;
}>();

const router = useRouter();
const { activeTemplates, isLoading, loadActiveTemplates } = useTemplateDefinitions();

const selectedTemplate = ref<TemplateDefinition | null>(null);
const additionalInstructions = ref("");
const editMode = ref(false);

// Computed Properties
const dialogTitle = computed(() =>
    editMode.value
        ? "Manage Templates"
        : "Select a Template for Writing Demand Letter"
);

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


// Methods
const toggleEditMode = () => {
    editMode.value = !editMode.value;
    if (editMode.value) {
        selectedTemplate.value = null;
    }
};

const handleConfirm = () => {
    // If in edit mode, just close edit mode
    if (editMode.value) {
        editMode.value = false;
        return;
    }

    // Original selection logic
    if (!selectedTemplate.value) {
        return;
    }

    emit("confirm", selectedTemplate.value, additionalInstructions.value);
};

// Load templates when component mounts
onMounted(loadActiveTemplates);

</script>
