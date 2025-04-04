<template>
	<ListTransition :class="dense ? 'space-y-2' : 'space-y-4'">
		<QSkeleton v-if="!artifacts" class="h-20 my-2" />
		<div v-else-if="artifacts.length === 0" class="text-xl text-center text-gray-500">No Artifacts</div>
		<template v-else>
			<div class="flex-x gap-2" :class="dense ? 'mb-4' : 'mb-8'">
				<div class="flex-grow text-lg" :class="titleClass">{{ title }}</div>
				<ShowHideButton
					v-if="hasJson"
					v-model="isShowingJson"
					class="bg-purple-700 flex-shrink-0"
					size="sm"
					:show-icon="JsonIcon"
				/>
				<ShowHideButton
					v-if="hasText"
					v-model="isShowingText"
					class="bg-green-900 flex-shrink-0"
					size="sm"
					:show-icon="TextIcon"
				/>
				<ShowHideButton
					v-if="hasFiles"
					v-model="isShowingFiles"
					class="bg-amber-900 flex-shrink-0"
					size="sm"
					:show-icon="FilesIcon"
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
				:show-json="isShowingJson"
				:show-files="isShowingFiles"
				:artifact="artifact"
			/>
		</template>
	</ListTransition>
</template>
<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard";
import { Artifact } from "@/types";
import { FaSolidDatabase as JsonIcon, FaSolidFile as FilesIcon, FaSolidT as TextIcon } from "danx-icon";
import { ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	title?: string;
	titleClass?: string;
	artifacts?: Artifact[];
	dense?: boolean;
}>(), {
	titleClass: "",
	title: "Artifacts",
	artifacts: null
});

const isShowingAll = ref(false);

const hasText = computed(() => props.artifacts?.some((artifact) => artifact.text_content));
const hasJson = computed(() => props.artifacts?.some((artifact) => artifact.json_content));
const hasFiles = computed(() => props.artifacts?.some((artifact) => artifact.files?.length > 0));
const isShowingText = ref(false);
const isShowingJson = ref(false);
const isShowingFiles = ref(false);
</script>
