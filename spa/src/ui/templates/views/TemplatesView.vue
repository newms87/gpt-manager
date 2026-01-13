<template>
    <UiMainLayout>
        <template #header>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800">
                            Templates
                        </h1>
                        <p class="text-slate-600 mt-1">
                            Create and manage document templates for your demands
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <!-- Main Content - Now just the shared component -->
        <TemplateManagementList
            empty-state-title="No templates yet"
            empty-state-description="Get started by creating your first document template"
        />
    </UiMainLayout>
</template>

<script setup lang="ts">
import { UiMainLayout } from "../../shared";
import TemplateManagementList from "../components/TemplateManagementList.vue";
</script>
