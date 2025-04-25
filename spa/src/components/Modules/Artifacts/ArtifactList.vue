<template>
	<ListTransition :class="dense ? 'space-y-2' : 'space-y-4'">
		<QSkeleton v-if="!artifacts" class="h-20 my-2" />
		<div v-else-if="artifacts.length === 0" class="text-xl text-center text-gray-500">No Artifacts</div>
		<template v-else>
			<div class="flex-x gap-2" :class="dense ? 'mb-4' : 'mb-8'">
				<div class="flex-grow text-lg" :class="titleClass">{{ computedTitle }}</div>
				<ShowHideButton
					v-if="hasText"
					v-model="isShowingText"
					class="bg-green-900 flex-shrink-0"
					size="sm"
					:show-icon="TextIcon"
					tooltip="Show All Text"
				/>
				<ShowHideButton
					v-if="hasFiles"
					v-model="isShowingFiles"
					class="bg-amber-900 flex-shrink-0"
					size="sm"
					:show-icon="FilesIcon"
					tooltip="Show All Files"
				/>
				<ShowHideButton
					v-if="hasJson"
					v-model="isShowingJson"
					class="bg-purple-700 flex-shrink-0"
					size="sm"
					:show-icon="JsonIcon"
					tooltip="Show All Json Content"
				/>
				<ShowHideButton
					v-if="hasMeta"
					v-model="isShowingMeta"
					class="bg-slate-500 text-slate-300 flex-shrink-0"
					size="sm"
					:show-icon="MetaIcon"
					tooltip="Show All Artifact Meta"
				/>
				<ShowHideButton
					v-if="hasGroup"
					v-model="isShowingGroup"
					class="bg-indigo-700 flex-shrink-0"
					size="sm"
					:show-icon="GroupIcon"
					tooltip="Show All Child Artifacts"
				/>
				<ShowHideButton
					v-model="isShowingAll"
					class="bg-sky-900"
					icon-class="w-5"
					label-class="text-base ml-2"
					:label="isShowingAll ? 'Collapse All' : 'Expand All'"
					size="sm"
				/>
			</div>
			<ArtifactCard
				v-for="artifact in artifacts"
				:key="artifact.id"
				:show="isShowingAll"
				:show-text="isShowingText"
				:show-files="isShowingFiles"
				:show-json="isShowingJson"
				:show-meta="isShowingMeta"
				:show-group="isShowingGroup"
				:artifact="artifact"
				:level="level"
			/>
		</template>
	</ListTransition>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidLayerGroup as GroupIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { AnyObject, ListControlsPagination, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, shallowRef } from "vue";

const props = withDefaults(defineProps<{
	title?: string;
	titleClass?: string;
	filter?: AnyObject;
	dense?: boolean;
	level?: number;
}>(), {
	titleClass: "",
	title: null,
	filter: null,
	level: 0
});

const artifacts = shallowRef([]);
const isShowingAll = ref(false);

const hasText = computed(() => artifacts.value.some((artifact) => artifact.text_content));
const hasFiles = computed(() => artifacts.value.some((artifact) => artifact.files?.length > 0));
const hasJson = computed(() => artifacts.value.some((artifact) => artifact.json_content));
const hasMeta = computed(() => artifacts.value.some((artifact) => artifact.meta));
const hasGroup = computed(() => artifacts.value.some((artifact) => (artifact.child_artifacts_count || 0) > 0));
const isShowingText = ref(false);
const isShowingFiles = ref(false);
const isShowingJson = ref(false);
const isShowingMeta = ref(false);
const isShowingGroup = ref(false);

const computedTitle = computed(() => props.title === null ? (props.level ? `Level ${props.level} Artifacts` : "Top-Level Artifacts") : props.title);

const artifactsField = {
	text_content: true,
	json_content: true,
	meta: true,
	files: { transcodes: true, thumb: true }
};

async function loadArtifacts() {
	const results = await dxArtifact.routes.list({
		filter: props.filter,
		fields: artifactsField
	} as ListControlsPagination);

	artifacts.value = results.data;
	console.log("got results", results.data);
}

loadArtifacts();
</script>
