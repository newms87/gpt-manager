<template>
    <div class="overflow-hidden rounded" :class="avatar.messageClass">
        <div class="flex items-center">
            <div>
                <QBtn
                    :disable="readonly"
                    @click="updateAction.trigger(message, {role: nextRole[message.role] ?? 'user'})"
                >
                    <div class="rounded-full p-1 w-6 h-6 flex items-center justify-center" :class="avatar.class">
                        <component :is="avatar.icon" class="w-3 text-slate-300" :class="avatar.iconClass" />
                    </div>
                    <QTooltip>Toggle message role</QTooltip>
                </QBtn>
            </div>
            <div class="font-bold text-slate-400 ml-1 mr-2 flex-grow">
                <EditOnClickTextField
                    :readonly="readonly"
                    editing-class="bg-slate-600"
                    :model-value="message.title || fDateTime(message.created_at)"
                    @update:model-value="updateDebouncedAction.trigger(message, {title: $event})"
                />
            </div>
            <div class="text-xs text-slate-400 mr-2 whitespace-nowrap">
                {{ fDateTime(message.timestamp) }}
            </div>
            <ListTransition class="text-slate-300">
                <ShowHideButton v-model="showMessage" :name="'thread-message-' + message.id" />
                <ShowHideButton
                    v-if="isUserMessage"
                    v-model="showFiles"
                    :name="'thread-files-' + message.id"
                    class="mr-2"
                    :label="files.length || 0"
                    :show-icon="AddImageIcon"
                    :hide-icon="HideImageIcon"
                    tooltip="Show / Hide Images"
                />
                <ShowHideButton
                    v-if="!isUserMessage"
                    v-model="showApiLog"
                    :name="'api-log-' + message.id"
                    :show-icon="ApiLogIcon"
                    :hide-icon="ApiLogIcon"
                    :disabled="!message.api_log_id"
                    :loading="isLoadingApiLog"
                    tooltip="Show / Hide API Log"
                    @show="loadApiLog"
                />
                <ShowHideButton
                    v-model="showMetaFields"
                    name="show-meta-fields"
                    :show-icon="ToggleMetaFieldsIcon"
                    :hide-icon="ToggleMetaFieldsIcon"
                    class="bg-transparent"
                    :color="showMetaFields ? 'sky-invert' : ''"
                />
                <template v-if="!readonly">
                    <ActionButton
                        :action="deleteAction"
                        :target="message"
                        type="trash"
                        class="mr-2"
                        tooltip="Delete message"
                    />
                    <ActionButton
                        v-if="thread"
                        :action="resetToMessageAction"
                        :target="thread"
                        :input="{ message_id: message.id }"
                        type="refresh"
                        class="mr-2"
                        tooltip="Reset messages to here"
                    />
                </template>
            </ListTransition>
        </div>

        <template v-if="showMessage">
            <QSeparator class="bg-slate-500 mx-3" />
            <div class="text-sm flex-grow m-3">
                <MarkdownEditor
                    v-model="content"
                    sync-model-changes
                    :readonly="readonly"
                    editor-class="text-slate-200 bg-slate-800 rounded"
                    :format="isJSON(content) ? 'yaml' : 'text'"
                    @update:model-value="updateDebouncedAction.trigger(message, {content})"
                />
                <template v-if="message.data">
                    <div class="text-sm font-bold mt-3 mb-2">Data Content (read only)</div>
                    <MarkdownEditor
                        readonly
                        :model-value="message.data"
                        sync-model-changes
                    />
                </template>
                <div v-if="summary">
                    <div class="text-sm font-bold mt-3 mb-2">Summary</div>
                    <MarkdownEditor
                        v-model="summary"
                        :readonly="readonly"
                        sync-model-changes
                        @update:model-value="updateDebouncedAction.trigger(message, {summary})"
                    />
                </div>
            </div>
        </template>
        <template v-if="showFiles && isUserMessage">
            <MultiFileField
                v-model="files"
                :readonly="readonly"
                @update:model-value="saveFilesAction.trigger(message, { ids: files.map(f => f.id) })"
            />
        </template>
        <template v-if="showApiLog && message.api_log_id">
            <QSeparator class="bg-slate-500 mx-3" />
            <div class="m-3">
                <QSkeleton v-if="isLoadingApiLog" class="h-32" />
                <OpenAiResponsesApiCard v-else-if="message.apiLog && isOpenAiResponsesApi" :api-log="message.apiLog" />
                <ApiLogEntryCard v-else-if="message.apiLog" :api-log="message.apiLog" />
            </div>
        </template>
    </div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import ApiLogEntryCard from "@/components/Modules/Audits/ApiLogs/ApiLogEntryCard.vue";
import OpenAiResponsesApiCard from "@/components/Modules/Audits/ApiLogs/OpenAiResponsesApiCard.vue";
import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import { dxThreadMessage } from "@/components/Modules/Agents/Threads/ThreadMessage/config";
import { AgentThread, AgentThreadMessage } from "@/types";
import {
    FaRegularImage as HideImageIcon,
    FaRegularUser as UserIcon,
    FaSolidFilePen as ToggleMetaFieldsIcon,
    FaSolidImage as AddImageIcon,
    FaSolidRobot as AssistantIcon,
    FaSolidServer as ApiLogIcon
} from "danx-icon";
import {
    ActionButton,
    AnyObject,
    EditOnClickTextField,
    fDateTime,
    isJSON,
    ListTransition,
    MultiFileField,
    ShowHideButton,
    UploadedFile
} from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
    message: AgentThreadMessage;
    thread?: AgentThread;
    readonly?: boolean;
    isMessageExpanded?: boolean;
    isFilesExpanded?: boolean;
}>(), {
    isMessageExpanded: undefined,
    isFilesExpanded: undefined
});

const emit = defineEmits<{
    'update:messageExpanded': [value: boolean];
    'update:filesExpanded': [value: boolean];
}>();

const showMetaFields = ref(false);
const content = ref(getFilteredContent());
const summary = ref(props.message.summary);
const files = ref<UploadedFile[]>(props.message.files || []);

const showMessage = ref(true);
const showFiles = ref(files.value.length > 0);
const showApiLog = ref(false);
const isLoadingApiLog = ref(false);

// Sync local showMessage with parent prop when provided
watch(() => props.isMessageExpanded, (value) => {
    if (value !== undefined) {
        showMessage.value = value;
    }
}, { immediate: true });

// Sync local showFiles with parent prop when provided
watch(() => props.isFilesExpanded, (value) => {
    if (value !== undefined) {
        showFiles.value = value;
    }
}, { immediate: true });

// Emit changes when local state changes
watch(showMessage, (value) => {
    emit('update:messageExpanded', value);
});

watch(showFiles, (value) => {
    emit('update:filesExpanded', value);
});

// Load API log on demand (routes.details auto-hydrates props.message.apiLog)
async function loadApiLog() {
    if (!props.message.apiLog && !isLoadingApiLog.value) {
        isLoadingApiLog.value = true;
        try {
            await dxThreadMessage.routes.details(props.message, { apiLog: true });
        } finally {
            isLoadingApiLog.value = false;
        }
    }
}

const isUserMessage = computed(() => props.message.role === "user");
const isOpenAiResponsesApi = computed(() => {
    return props.message.apiLog?.url?.includes('api.openai.com/v1/responses');
});
const nextRole = {
    user: "assistant",
    assistant: "user"
};
const avatar = computed<{
    icon: object;
    class: string;
    iconClass?: string;
    messageClass?: string;
}>(() => {
    switch (props.message.role) {
        case "user":
            return { icon: UserIcon, class: "bg-lime-700", messageClass: "bg-lime-900" };
        case "assistant":
            return { icon: AssistantIcon, class: "bg-sky-600", iconClass: "w-4", messageClass: "bg-sky-800" };
        default:
            return { icon: UserIcon, class: "bg-red-700", messageClass: "bg-red-900" };
    }
});

const deleteAction = dxThreadMessage.getAction("delete");
const resetToMessageAction = dxAgentThread.getAction("reset-to-message");
const updateAction = dxThreadMessage.getAction("update");
const saveFilesAction = dxThreadMessage.getAction("save-files");
const updateDebouncedAction = dxThreadMessage.getAction("update-debounced");

// Property meta filtering
watch(() => showMetaFields.value, () => {
    content.value = getFilteredContent();
});

function getFilteredContent() {
    let content = props.message.content;
    if (!showMetaFields.value && isJSON(content)) {
        if (typeof content === "string") {
            content = JSON.parse(content);
        }

        return filterPropertyMeta(content as AnyObject);
    }

    return content;
}

/**
 *  Recursively searches the property keys of the object and removes the property_meta field from each object
 */
function filterPropertyMeta(content: AnyObject = null) {
    if (!content || typeof content !== "object") return content;
    if (Array.isArray(content)) {
        return content.map(filterPropertyMeta);
    }

    const newContent: AnyObject = {};
    for (const key in content) {
        if (key === "property_meta") continue;
        newContent[key] = filterPropertyMeta(content[key]);
    }
    return newContent;
}
</script>
