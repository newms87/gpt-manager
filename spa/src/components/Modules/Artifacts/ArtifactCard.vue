<template>
	<div class="bg-slate-900 p-2 rounded-lg">
		<div class="flex-x mb-2 space-x-2 w-full max-w-full overflow-hidden">
			<LabelPillWidget :label="`Artifact: ${artifact.id}`" color="sky" size="xs" class="flex-shrink-0" />
			<LabelPillWidget :label="fDateTime(artifact.created_at)" color="blue" size="xs" class="flex-shrink-0" />
			<LabelPillWidget :label="artifact.position" color="green" size="xs" class="flex-shrink-0" />
			<div class="flex-grow min-w-0 overflow-hidden">{{ artifact.name }}</div>
			<ShowHideButton
				v-if="artifact.text_content"
				v-model="isShowingText"
				class="bg-green-900 flex-shrink-0"
				size="sm"
				:show-icon="TextIcon"
				tooltip="Show Text"
			/>
			<ShowHideButton
				v-if="artifact.files?.length > 0"
				v-model="isShowingFiles"
				class="bg-amber-900 flex-shrink-0"
				size="sm"
				:show-icon="FilesIcon"
				tooltip="Show Files"
			/>
			<ShowHideButton
				v-if="artifact.json_content"
				v-model="isShowingJson"
				class="bg-purple-700 flex-shrink-0"
				size="sm"
				:show-icon="JsonIcon"
				tooltip="Show Json Content"
			/>
			<ShowHideButton
				v-if="artifact.meta"
				v-model="isShowingMeta"
				class="bg-slate-500 text-slate-300 flex-shrink-0"
				size="sm"
				:show-icon="MetaIcon"
				tooltip="Show Artifact Meta"
			/>
			<ShowHideButton
				v-if="typeCount > 1"
				:model-value="isShowingAll"
				class="bg-sky-900 flex-shrink-0"
				size="sm"
				tooltip="Show All Data"
				@update:model-value="onToggleAll()"
			/>
		</div>
		<ListTransition>
			<div v-if="artifact.files?.length && isShowingFiles">
				<div class="flex items-stretch justify-start">
					<FilePreview
						v-for="file in artifact.files"
						:key="'file-upload-' + file.id"
						class="cursor-pointer bg-gray-200 w-16 h-16 m-1"
						:file="file"
						:related-files="file.transcodes"
						downloadable
					/>
				</div>
			</div>
			<div v-if="artifact.text_content && isShowingText">
				<MarkdownEditor
					:model-value="artifact.text_content"
					format="text"
					readonly
				/>
			</div>
			<div v-if="artifact.json_content && isShowingJson">
				<MarkdownEditor
					:model-value="artifact.json_content"
					format="yaml"
					readonly
				/>
			</div>
			<div v-if="artifact.meta && isShowingMeta">
				<MarkdownEditor
					:model-value="artifact.meta"
					format="yaml"
					readonly
				/>
			</div>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { Artifact } from "@/types";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { fDateTime, FilePreview, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	artifact: Artifact,
	show?: boolean;
	showText?: boolean;
	showFiles?: boolean;
	showJson?: boolean;
	showMeta?: boolean;
}>();

const hasText = computed(() => !!props.artifact.text_content);
const hasFiles = computed(() => !!props.artifact.files?.length);
const hasJson = computed(() => !!props.artifact.json_content);
const hasMeta = computed(() => !!props.artifact.meta);
const typeCount = computed(() => [hasText.value, hasJson.value, hasFiles.value].filter(Boolean).length);
const isShowingText = ref(props.showText);
const isShowingFiles = ref(props.showFiles);
const isShowingJson = ref(props.showJson);
const isShowingMeta = ref(props.showMeta);

const isShowingAll = computed(() => (!hasText.value || isShowingText.value) && (!hasFiles.value || isShowingFiles.value) && (!hasJson.value || isShowingJson.value) && (!hasMeta.value || isShowingMeta.value));
function onToggleAll(state: boolean = null) {
	state = state === null ? !isShowingAll.value : state;
	isShowingText.value = state;
	isShowingFiles.value = state;
	isShowingJson.value = state;
	isShowingMeta.value = state;
}

watch(() => props.show, onToggleAll);
watch(() => props.showText, (state) => {
	isShowingText.value = state;
});
watch(() => props.showFiles, (state) => {
	isShowingFiles.value = state;
});
watch(() => props.showJson, (state) => {
	isShowingJson.value = state;
});
watch(() => props.showMeta, (state) => {
	isShowingMeta.value = state;
});
</script>
