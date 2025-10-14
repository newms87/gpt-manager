<template>
    <div class="space-y-4">
        <div class="flex items-start gap-2 text-sm text-slate-600 bg-purple-50 border border-purple-200 rounded-lg p-3">
            <FaSolidCircleInfo class="w-4 mt-0.5 text-purple-500 flex-shrink-0" />
            <div>
                AI will resolve this variable based on artifacts and context. Add specific instructions to guide the AI.
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700 mb-2 block">
                AI Instructions (Optional)
            </label>
            <MarkdownEditor
                :model-value="modelValue.ai_instructions || ''"
                placeholder="e.g., Extract the patient's full name from the medical records..."
                class="w-full"
                @update:model-value="updateInstructions"
            />
            <div class="text-xs text-slate-500 mt-1">
                Provide specific instructions to help the AI understand how to resolve this variable.
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { FaSolidCircleInfo } from "danx-icon";
import type { TemplateVariable } from "../types";

const modelValue = defineModel<TemplateVariable>({ required: true });

const emit = defineEmits<{
    "update:ai-instructions": [value: string];
}>();

const updateInstructions = (value: string) => {
    // Update local model for immediate UI feedback
    modelValue.value.ai_instructions = value || undefined;
    // Emit field-specific change
    emit("update:ai-instructions", value);
};
</script>
