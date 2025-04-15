<template>
	<div class="bg-slate-900 p-2 rounded-lg">
		<div class="flex-x mb-2 space-x-2 w-full max-w-full overflow-hidden">
			<LabelPillWidget :label="idLabel" color="sky" size="xs" class="flex-shrink-0" />
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
				v-if="artifact.child_artifacts_count > 0"
				v-model="isShowingGroup"
				class="bg-indigo-700 flex-shrink-0"
				size="sm"
				:show-icon="GroupIcon"
				tooltip="Show Child Artifacts"
				:label="artifact.child_artifacts_count"
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
			<ArtifactList
				v-if="isShowingGroup"
				:artifacts="childArtifacts"
				dense
				class="bg-slate-800 p-4"
				:level="(level||0)+1"
			/>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import { Artifact } from "@/types";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidLayerGroup as GroupIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { fDateTime, FilePreview, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, shallowRef, watch } from "vue";

const props = defineProps<{
	artifact: Artifact,
	show?: boolean;
	showText?: boolean;
	showFiles?: boolean;
	showJson?: boolean;
	showMeta?: boolean;
	showGroup?: boolean;
	level?: number;
}>();

const hasText = computed(() => !!props.artifact.text_content);
const hasFiles = computed(() => !!props.artifact.files?.length);
const hasJson = computed(() => !!props.artifact.json_content);
const hasMeta = computed(() => !!props.artifact.meta);
const hasGroup = computed(() => (props.artifact.child_artifacts_count || 0) > 0);
const typeCount = computed(() => [hasText.value, hasJson.value, hasFiles.value, hasGroup.value].filter(Boolean).length);
const isShowingText = ref(props.showText);
const isShowingFiles = ref(props.showFiles);
const isShowingJson = ref(props.showJson);
const isShowingMeta = ref(props.showMeta);
const isShowingGroup = ref(props.showGroup);

const childArtifacts = shallowRef([]);

const idLabel = computed(() => "Artifact: " + (props.artifact.original_artifact_id ? props.artifact.original_artifact_id + " -> " : "") + props.artifact.id);

const isShowingAll = computed(() =>
	(!hasText.value || isShowingText.value) &&
	(!hasFiles.value || isShowingFiles.value) &&
	(!hasJson.value || isShowingJson.value) &&
	(!hasMeta.value || isShowingMeta.value) &&
	(!hasGroup.value || isShowingGroup.value)
);
function onToggleAll(state: boolean = null) {
	state = state === null ? !isShowingAll.value : state;
	isShowingText.value = state;
	isShowingFiles.value = state;
	isShowingJson.value = state;
	isShowingMeta.value = state;
	isShowingGroup.value = state;
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
watch(() => props.showGroup, (state) => {
	isShowingGroup.value = state;
});
watch(() => isShowingGroup.value, () => {
	if (isShowingGroup.value) {
		loadChildArtifacts();
	}
});

async function loadChildArtifacts() {
	const artifactsField = {
		text_content: true,
		json_content: true,
		meta: true,
		files: { transcodes: true, thumb: true }
	};

	const { data } = await dxArtifact.routes.list({
		filter: {
			parent_artifact_id: props.artifact.id
		},
		fields: artifactsField
	}, { abortOn: "child-artifacts:" + props.artifact.id });
	childArtifacts.value = data;
}
</script>
