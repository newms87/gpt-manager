<template>
    <UiMainLayout>
        <template #header>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800">
                            Demand Templates
                        </h1>
                        <p class="text-slate-600 mt-1">
                            Create and manage document templates for your demands
                        </p>
                    </div>

                    <ActionButton
                        type="create"
                        label="New Template"
                        size="lg"
                        color="sky"
                        rounded
                        :action="createAction"
                        :input="newTemplateData"
                    />
                </div>
            </div>
        </template>

        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Loading State -->
            <div v-if="dxDemandTemplate.isLoadingList.value" class="flex justify-center py-12">
                <QSpinner size="lg" color="blue" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!templates.length" class="text-center py-12">
                <div class="bg-white rounded-lg p-8 max-w-md mx-auto border border-slate-200 shadow-sm">
                    <FaSolidClipboard class="w-16 h-16 text-slate-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-slate-800 mb-2">No templates yet</h3>
                    <p class="text-slate-600 mb-4">Get started by creating your first document template</p>
                    <ActionButton
                        type="create"
                        label="Create Template"
                        color="sky"
                        :action="createAction"
                        :input="newTemplateData"
                    />
                </div>
            </div>

            <!-- Templates List -->
            <div v-else class="space-y-4">
                <TemplateCard
                    v-for="template in templates"
                    :key="template.id"
                    :template="template"
                    @update="handleTemplateUpdate"
                    @delete="handleTemplateDelete"
                />
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
    </UiMainLayout>
</template>

<script setup lang="ts">
import { FaSolidClipboard } from "danx-icon";
import { QSpinner } from "quasar";
import { ActionButton, ConfirmDialog } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";
import { UiMainLayout } from "../../shared";
import TemplateCard from "../components/TemplateCard.vue";
import { dxDemandTemplate } from "../config";
import type { DemandTemplate } from "../types";


// State
const templateToDelete = ref<DemandTemplate | null>(null);
const templates = ref([] as DemandTemplate[]);
// Reload list function
const reloadList = async () => {
    templates.value = (await dxDemandTemplate.routes.list({ fields: { template_variables: true } }))?.data || [];
    console.log("templates", templates.value);
};

// Load templates with variables on mount
onMounted(reloadList);

// Actions
const createAction = dxDemandTemplate.getAction("create", { onFinish: reloadList });
const updateAction = dxDemandTemplate.getAction("update");
const deleteAction = dxDemandTemplate.getAction("delete", { onFinish: reloadList });

const generateTemplateName = () => {
    const count = templates.value.length + 1;
    return `Template ${count}`;
};

const newTemplateData = computed(() => ({
    name: generateTemplateName(),
    description: "",
    template_url: "",
    is_active: true
}));

// Event handlers
const handleTemplateUpdate = (template: DemandTemplate, data: Partial<DemandTemplate>) => {
    updateAction.trigger(template, data);
};

const handleTemplateDelete = (template: DemandTemplate) => {
    templateToDelete.value = template;
};

const confirmDelete = () => {
    if (templateToDelete.value) {
        deleteAction.trigger(templateToDelete.value);
        templateToDelete.value = null;
    }
};
</script>
