<template>
    <div class="template-management-list h-full">
        <!-- Loading State -->
        <div v-if="isLoadingTemplates" class="flex justify-center py-12">
            <QSpinner size="lg" color="blue" />
        </div>

        <!-- Empty State -->
        <div v-else-if="!displayTemplates.length" class="text-center py-12">
            <div class="bg-white rounded-lg p-8 max-w-md mx-auto border border-slate-200 shadow-sm">
                <FaSolidClipboard class="w-16 h-16 text-slate-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-slate-800 mb-2">{{ emptyStateTitle }}</h3>
                <p class="text-slate-600 mb-4">{{ emptyStateDescription }}</p>
                <div v-if="showCreateButton" class="flex justify-center gap-3">
                    <ActionButton
                        type="create"
                        label="Google Docs Template"
                        color="sky"
                        :action="createAction"
                        :input="newGoogleDocsTemplateData"
                    />
                    <ActionButton
                        type="create"
                        label="HTML Template"
                        color="purple"
                        :action="createHtmlAction"
                        :input="newHtmlTemplateData"
                    />
                </div>
            </div>
        </div>

        <!-- Templates List -->
        <div v-else class="h-full flex flex-col">
            <!-- Header with Filter and Create Buttons (fixed) -->
            <div v-if="showCreateButton && displayTemplates.length > 0" class="flex justify-between items-center mb-4 flex-shrink-0">
                <!-- Type Filter -->
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600">Filter:</span>
                    <div class="flex rounded-lg overflow-hidden border border-slate-300">
                        <button
                            class="px-3 py-1.5 text-sm transition-colors"
                            :class="typeFilter === null ? 'bg-slate-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                            @click="typeFilter = null"
                        >
                            All
                        </button>
                        <button
                            class="px-3 py-1.5 text-sm transition-colors border-l border-slate-300"
                            :class="typeFilter === 'google_docs' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                            @click="typeFilter = 'google_docs'"
                        >
                            Google Docs
                        </button>
                        <button
                            class="px-3 py-1.5 text-sm transition-colors border-l border-slate-300"
                            :class="typeFilter === 'html' ? 'bg-purple-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                            @click="typeFilter = 'html'"
                        >
                            HTML
                        </button>
                    </div>
                </div>

                <!-- Create Buttons -->
                <div class="flex gap-2">
                    <ActionButton
                        type="create"
                        label="Google Docs"
                        tooltip="Create Google Docs Template"
                        color="sky"
                        size="sm"
                        :action="createAction"
                        :input="newGoogleDocsTemplateData"
                    />
                    <ActionButton
                        type="create"
                        label="HTML"
                        tooltip="Create HTML Template"
                        color="purple"
                        size="sm"
                        :action="createHtmlAction"
                        :input="newHtmlTemplateData"
                    />
                </div>
            </div>

            <!-- Template Cards (scrollable) -->
            <div class="flex-grow overflow-auto space-y-4">
                <TemplateCard
                    v-for="template in filteredTemplates"
                    :key="template.id"
                    :template="template"
                    @update="handleTemplateUpdate"
                    @delete="handleTemplateDelete"
                    @edit-builder="handleEditBuilder"
                />

                <!-- No results after filtering -->
                <div v-if="filteredTemplates.length === 0 && displayTemplates.length > 0" class="text-center py-8 text-slate-500">
                    No {{ typeFilter === 'html' ? 'HTML' : 'Google Docs' }} templates found.
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <ConfirmDialog
            v-if="templateToDelete"
            class="ui-mode"
            title="Delete Template?"
            :content="`Are you sure you want to delete &quot;${templateToDelete.name}&quot;? This action cannot be undone.`"
            color="negative"
            @confirm="confirmDelete"
            @close="templateToDelete = null"
        />
    </div>
</template>

<script setup lang="ts">
import { FaSolidClipboard } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { dxTemplateDefinition } from "../config";
import { useTemplateDefinitions } from "../composables/useTemplateDefinitions";
import type { TemplateDefinition, TemplateType } from "../types";
import TemplateCard from "./TemplateCard.vue";

const props = withDefaults(defineProps<{
    emptyStateTitle?: string;
    emptyStateDescription?: string;
    showCreateButton?: boolean;
    isLoading?: boolean;
    templates?: TemplateDefinition[];
}>(), {
    emptyStateTitle: "No templates yet",
    emptyStateDescription: "Get started by creating your first document template",
    showCreateButton: true,
    isLoading: false,
    templates: undefined
});

const router = useRouter();

// Composable for template management
const { activeTemplates, isLoading: composableLoading, loadActiveTemplates } = useTemplateDefinitions();

// State
const templateToDelete = ref<TemplateDefinition | null>(null);
const typeFilter = ref<TemplateType | null>(null);

// Computed
const isLoadingTemplates = computed(() => props.isLoading || composableLoading.value);
const displayTemplates = computed(() => props.templates ?? activeTemplates.value);

const filteredTemplates = computed(() => {
    if (!typeFilter.value) return displayTemplates.value;
    return displayTemplates.value.filter((t: TemplateDefinition) => t.type === typeFilter.value);
});

const newGoogleDocsTemplateData = computed(() => ({
    name: "Google Docs Template",
    description: "",
    type: "google_docs" as TemplateType,
    template_url: "",
    is_active: true
}));

const newHtmlTemplateData = computed(() => ({
    name: "HTML Template",
    description: "",
    type: "html" as TemplateType,
    html_content: "<div>\n  <h1>{{title}}</h1>\n  <p>{{content}}</p>\n</div>",
    css_content: "",
    is_active: true
}));

// Actions
const createAction = dxTemplateDefinition.getAction("create", {
    onFinish: loadActiveTemplates
});

const createHtmlAction = dxTemplateDefinition.getAction("create", {
    onFinish: (result: { item: TemplateDefinition }) => {
        // Navigate to builder for HTML templates
        if (result?.item?.id) {
            router.push({ name: "ui.template-builder", params: { id: result.item.id } });
        }
    }
});

const updateAction = dxTemplateDefinition.getAction("update");
const deleteAction = dxTemplateDefinition.getAction("delete", {
    onFinish: loadActiveTemplates
});

// Methods
const handleTemplateUpdate = (template: TemplateDefinition, data: Partial<TemplateDefinition>) => {
    updateAction.trigger(template, data);
};

const handleTemplateDelete = (template: TemplateDefinition) => {
    templateToDelete.value = template;
};

const handleEditBuilder = (template: TemplateDefinition) => {
    router.push({ name: "ui.template-builder", params: { id: template.id } });
};

const confirmDelete = async () => {
    if (templateToDelete.value) {
        await deleteAction.trigger(templateToDelete.value);
        templateToDelete.value = null;
    }
};

// Load templates on mount (only if not passed via props)
onMounted(() => {
    if (!props.templates) {
        loadActiveTemplates();
    }
});
</script>
