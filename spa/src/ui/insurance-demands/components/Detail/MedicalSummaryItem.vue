<template>
    <div class="border border-teal-200 rounded-lg bg-white">
        <!-- Summary Header -->
        <div class="px-4 py-3 border-b border-teal-100 bg-teal-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <FaSolidFile class="w-4 h-4 text-teal-600" />
                    <h4 class="text-sm font-medium text-teal-800">
                        {{ summary.name || `Medical Summary ${index + 1}` }}
                    </h4>
                </div>
                <div class="flex items-center space-x-2">
                    <span v-if="summary.type" class="text-xs text-teal-600 bg-teal-100 px-2 py-1 rounded">
                        {{ summary.type }}
                    </span>
                    <ShowHideButton
                        v-if="isExpanded"
                        v-model="isEditing"
                        type="edit"
                        color="teal"
                        size="xs"
                        :show-icon="EditIcon"
                        tooltip="Edit"
                    />
                    <ShowHideButton
                        v-model="isExpanded"
                        size="xs"
                        color="teal-invert"
                    />
                    <ActionButton
                        type="trash"
                        color="red"
                        size="xs"
                        tooltip="Delete"
                        @click="deleteItem"
                    />
                </div>
            </div>
            <p v-if="summary.created_at" class="text-xs text-teal-600 mt-1">
                Generated {{ fDateTime(summary.created_at, { format: "MMM d, yyyy h:mm a" }) }}
            </p>
        </div>

        <!-- Summary Content -->
        <div
            v-if="isExpanded"
            class="p-4"
        >
            <div v-if="isEditing">
                <!-- Editing Mode -->
                <div class="space-y-4">
                    <MarkdownEditor
                        v-model="editingContent"
                        :readonly="false"
                        :height="400"
                        class="border border-teal-200 rounded"
                    />
                    <div class="flex items-center justify-end space-x-2">
                        <ActionButton
                            type="cancel"
                            color="gray"
                            size="sm"
                            label="Cancel"
                            @click="cancelEditing"
                        />
                        <ActionButton
                            type="save"
                            color="teal"
                            size="sm"
                            label="Save"
                            @click="saveContent"
                        />
                    </div>
                </div>
            </div>
            <div v-else>
                <!-- View Mode -->
                <div v-if="summary.text_content">
                    <MarkdownEditor
                        :model-value="summary.text_content"
                        :readonly="true"
                        :height="400"
                        class="border border-teal-200 rounded"
                        :editor-class="{'bg-slate-100': !isEditing, 'bg-slate-200 text-slate-800': isEditing}"
                    />
                </div>
                <div v-else class="text-gray-500 italic p-4 bg-gray-50 rounded">
                    No content available
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MarkdownEditor } from "@/components/MarkdownEditor";
import { FaSolidFile, FaSolidPencil as EditIcon } from "danx-icon";
import { ActionButton, fDateTime, ShowHideButton } from "quasar-ui-danx";
import { ref, watch } from "vue";
import type { Artifact } from "../../../shared/types";

const props = defineProps<{
    summary: Artifact;
    index: number;
}>();

const emit = defineEmits<{
    "update": [summary: Artifact, content: string];
    "delete": [summary: Artifact];
}>();

// Local state
const isExpanded = ref(false);
const isEditing = ref(false);
const editingContent = ref("");

// Watch for editing mode changes to initialize content
watch(isEditing, (newValue) => {
    if (newValue) {
        editingContent.value = props.summary.text_content || "";
    }
});

// Cancel editing
const cancelEditing = () => {
    isEditing.value = false;
    editingContent.value = "";
};

// Save content
const saveContent = () => {
    emit("update", props.summary, editingContent.value);
    isEditing.value = false;
    editingContent.value = "";
};

// Delete item
const deleteItem = () => {
    emit("delete", props.summary);
};
</script>
