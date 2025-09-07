<template>
    <div class="bg-slate-800 rounded-lg px-3 py-1 cursor-default">
        <SelectionMenuField
            v-model:editing="isEditing"
            v-model:selected="promptDirective"
            selectable
            editable
            deletable
            name-editable
            creatable
            clearable
            label-class="text-slate-300"
            size="sm"
            :select-icon="DirectiveIcon"
            :options="promptDirectives"
            :loading="isRefreshing || loading"
            @create="createPromptDirectiveAction.trigger"
            @update="input => updatePromptDirectiveAction.trigger(promptDirective, input)"
            @delete="onDelete"
        />
        <div v-if="isEditing" class="mt-2">
            <MarkdownEditor
                :model-value="promptDirective.directive_text"
                :max-length="64000"
                @update:model-value="debouncedUpdatePromptDirectiveAction.trigger(promptDirective, {directive_text: $event})"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxPromptDirective } from "@/components/Modules/Prompts/Directives";
import { Agent, PromptDirective } from "@/types";
import { FaSolidFileLines as DirectiveIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits(["deleted"]);
defineProps<{
    loading: boolean;
}>();

const { loadItems, refreshItems, listItems: promptDirectives, isRefreshing } = dxPromptDirective.store;

// Immediately load prompt directives
loadItems();

const promptDirective = defineModel<PromptDirective>();
const isEditing = ref(false);

const createPromptDirectiveAction = dxPromptDirective.getAction("create-with-name");
const updatePromptDirectiveAction = dxPromptDirective.getAction("update");
const debouncedUpdatePromptDirectiveAction = dxPromptDirective.getAction("update", { debounce: 500 });
const deletePromptDirectiveAction = dxPromptDirective.getAction("delete-with-confirm", {
    onFinish: async () => {
        await refreshItems();
        emit("deleted");
    }
});

async function onDelete(deletedAgent: Agent) {
    const result = await deletePromptDirectiveAction.trigger(deletedAgent);
    if (result) {
        isEditing.value = false;
        if (promptDirective.value?.id === deletedAgent.id) {
            promptDirective.value = null;
        }
        await loadItems();
    }
}
</script>
