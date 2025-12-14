<template>
    <div class="bg-slate-800 rounded p-3 space-y-3">
        <!-- Header Pills -->
        <div class="flex items-center gap-2 flex-wrap">
            <LabelPillWidget
                v-if="requestData?.model"
                :label="requestData.model"
                color="purple"
                size="xs"
            />
            <LabelPillWidget
                v-if="requestData?.reasoning?.effort"
                :label="`Reasoning: ${requestData.reasoning.effort}`"
                color="amber"
                size="xs"
            />
            <LabelPillWidget
                v-if="requestData?.service_tier"
                :label="`Service: ${requestData.service_tier}`"
                color="slate"
                size="xs"
            />
        </div>

        <!-- Instructions -->
        <div v-if="requestData?.instructions">
            <div class="flex items-center gap-2 mb-2">
                <div class="text-sm font-bold">Instructions</div>
                <ShowHideButton v-model="showInstructions" label="" size="xs" color="sky-invert" />
            </div>
            <div v-if="showInstructions" class="text-sm">
                <div v-if="!showFullInstructions && instructionsPreview" class="mb-2">
                    {{ instructionsPreview }}
                    <ActionButton
                        label="Show more"
                        color="sky"
                        size="xs"
                        class="ml-2"
                        @click="showFullInstructions = true"
                    />
                </div>
                <div v-else class="whitespace-pre-wrap">
                    {{ requestData.instructions }}
                </div>
            </div>
        </div>

        <!-- Input Messages -->
        <div v-if="requestData?.input?.length">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-bold">Input Messages ({{ requestData.input.length }})</div>
                <ActionButton
                    :label="allMessagesExpanded ? 'Hide All' : 'Show All'"
                    color="sky"
                    size="xs"
                    @click="toggleAllMessages"
                />
            </div>
            <ListTransition>
                <div
                    v-for="(inputItem, index) in requestData.input"
                    :key="index"
                    class="bg-slate-900 rounded p-2 mb-2"
                >
                    <!-- Header row: Icon, Text toggle, Image toggle -->
                    <div class="flex items-center gap-2">
                        <!-- Role Icon -->
                        <div class="rounded-full p-1 w-6 h-6 flex items-center justify-center flex-shrink-0" :class="getRoleClass(inputItem.role)">
                            <component :is="getRoleIcon(inputItem.role)" class="w-3 text-slate-300" />
                        </div>

                        <!-- Text toggle -->
                        <ShowHideButton
                            v-if="getMessageTexts(inputItem).length > 0"
                            v-model="expandedMessageText[index]"
                            :show-icon="TextIcon"
                            :hide-icon="TextIcon"
                            label=""
                            size="xs"
                            color="sky-invert"
                        />

                        <!-- Image toggle -->
                        <ShowHideButton
                            v-if="getMessageImages(inputItem).length > 0"
                            v-model="expandedMessageFiles[index]"
                            :show-icon="ImageIcon"
                            :hide-icon="HideImageIcon"
                            :label="getMessageImages(inputItem).length"
                            size="xs"
                            color="sky-invert"
                        />

                        <!-- Preview when both collapsed -->
                        <div
                            v-if="!expandedMessageText[index] && !expandedMessageFiles[index]"
                            class="flex-grow min-w-0 flex items-center gap-1"
                        >
                            <!-- Small image thumbnails (up to 5) -->
                            <template v-for="(imgUrl, imgIndex) in getMessageImages(inputItem).slice(0, 5)" :key="imgIndex">
                                <FilePreview :src="imgUrl" class="w-6 h-6 flex-shrink-0 rounded" />
                            </template>
                            <span v-if="getMessageImages(inputItem).length > 5" class="text-xs text-slate-500 flex-shrink-0">
                                +{{ getMessageImages(inputItem).length - 5 }}
                            </span>
                            <!-- Text preview -->
                            <div class="text-sm text-slate-400 truncate">
                                {{ getMessagePreview(inputItem) }}
                            </div>
                        </div>
                    </div>

                    <!-- Images section -->
                    <div v-if="expandedMessageFiles[index] && getMessageImages(inputItem).length > 0" class="mt-3 flex flex-wrap gap-2">
                        <FilePreview
                            v-for="(imgUrl, imgIndex) in getMessageImages(inputItem)"
                            :key="imgIndex"
                            :src="imgUrl"
                            class="w-48 h-48 rounded cursor-pointer"
                        />
                    </div>

                    <!-- Text section -->
                    <div v-if="expandedMessageText[index] && getMessageTexts(inputItem).length > 0" class="mt-3 space-y-2">
                        <MarkdownEditor
                            v-for="(text, textIndex) in getMessageTexts(inputItem)"
                            :key="textIndex"
                            :model-value="text"
                            :format="isJSON(text) ? 'yaml' : 'text'"
                            readonly
                        />
                    </div>
                </div>
            </ListTransition>
        </div>

        <!-- Response Format -->
        <div v-if="requestData?.text?.format">
            <div class="flex items-center gap-2 mb-2">
                <div class="text-sm font-bold">Response Format</div>
                <ShowHideButton v-model="showResponseFormat" label="" size="xs" color="sky-invert" />
            </div>
            <div v-if="showResponseFormat" class="bg-slate-900 rounded p-2">
                <div class="text-xs mb-1">
                    Type: <span class="text-sky-300">{{ requestData.text.format.type }}</span>
                </div>
                <div v-if="requestData.text.format.name" class="text-xs mb-2">
                    Schema: <span class="text-sky-300">{{ requestData.text.format.name }}</span>
                </div>
                <MarkdownEditor
                    v-if="requestData.text.format.schema"
                    :model-value="JSON.stringify(requestData.text.format.schema, null, 2)"
                    format="yaml"
                    readonly
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import {
    FaRegularUser as UserIcon,
    FaSolidRobot as AssistantIcon,
    FaSolidImage as ImageIcon,
    FaRegularImage as HideImageIcon,
    FaSolidFileLines as TextIcon
} from "danx-icon";
import {
    ActionButton,
    FilePreview,
    isJSON,
    LabelPillWidget,
    ListTransition,
    ShowHideButton
} from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
    requestData: any
}>();

// Toggle states
const showInstructions = ref(true);
const showFullInstructions = ref(false);
const showResponseFormat = ref(false);
const expandedMessageFiles = ref<Record<number, boolean>>({});
const expandedMessageText = ref<Record<number, boolean>>({});

// Computed values
const instructionsPreview = computed(() => {
    if (!props.requestData?.instructions) return "";
    const instructions = props.requestData.instructions;
    if (instructions.length <= 200) {
        showFullInstructions.value = true;
        return "";
    }
    return instructions.substring(0, 200) + "...";
});

function getRoleIcon(role: string) {
    return role === 'assistant' ? AssistantIcon : UserIcon;
}

function getRoleClass(role: string) {
    return role === 'assistant' ? 'bg-sky-600' : 'bg-lime-700';
}

function getMessagePreview(inputItem: any): string {
    const textContent = inputItem.content?.find((c: any) => c.type === 'input_text');
    if (!textContent?.text) return '';
    return textContent.text.length > 100 ? textContent.text.substring(0, 100) + '...' : textContent.text;
}

function getMessageImages(inputItem: any): string[] {
    return inputItem.content
        ?.filter((c: any) => c.type === 'input_image')
        ?.map((c: any) => typeof c.image_url === 'string' ? c.image_url : c.image_url?.url)
        ?.filter(Boolean) || [];
}

function getMessageTexts(inputItem: any): string[] {
    return inputItem.content
        ?.filter((c: any) => c.type === 'input_text')
        ?.map((c: any) => c.text)
        ?.filter(Boolean) || [];
}

// Input messages toggle helpers
const allMessagesExpanded = computed(() => {
    const messageCount = props.requestData?.input?.length || 0;
    if (messageCount === 0) return false;

    for (let i = 0; i < messageCount; i++) {
        const inputItem = props.requestData.input[i];
        const hasText = getMessageTexts(inputItem).length > 0;
        const hasImages = getMessageImages(inputItem).length > 0;

        if (hasText && !expandedMessageText.value[i]) return false;
        if (hasImages && !expandedMessageFiles.value[i]) return false;
    }
    return true;
});

function toggleAllMessages() {
    const messageCount = props.requestData?.input?.length || 0;
    const shouldExpand = !allMessagesExpanded.value;

    for (let i = 0; i < messageCount; i++) {
        expandedMessageText.value[i] = shouldExpand;
        expandedMessageFiles.value[i] = shouldExpand;
    }
}
</script>
