<template>
	<div class="relative flex flex-col h-full">
		<ListTransition :class="dense ? 'space-y-2' : 'space-y-4'" class="flex-grow">
			<QSkeleton v-if="isLoading" class="h-20 my-2" />
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
					<ArtifactFilterBar v-model="filters" />
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

		<PaginationNavigator v-if="pagination.total > 0" v-model="pagination" class="bg-slate-800 py-4 px-6 shadow-lg" />
	</div>
</template>
<script setup lang="ts">
import PaginationNavigator from "@/components/Common/PaginationNavigator";
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import { Artifact } from "@/types";
import { PaginationModel } from "@/types/Pagination";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidLayerGroup as GroupIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { AnyObject, ListControlsPagination, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, shallowRef, watch } from "vue";
import ArtifactCard from "./ArtifactCard.vue";
import ArtifactFilterBar from "./ArtifactFilterBar.vue";

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

// Artifacts data
const artifacts = shallowRef<Artifact[]>([]);
const isLoading = ref(false);
const isShowingAll = ref(false);

// Show/hide controls
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

// Pagination state
const pagination = ref<PaginationModel>({
	page: 1,
	perPage: 20,
	total: 0
});

// Filter state (managed by ArtifactFilterBar component)
const filters = ref<AnyObject>({});

// Merge parent filter (like parent_artifact_id) with filters from filter component
const mergedFilters = computed(() => {
	return { ...props.filter, ...filters.value };
});

// Requested fields for artifacts
const artifactsField = {
	text_content: true,
	json_content: true,
	meta: true,
	files: { transcodes: true, thumb: true }
};

// Watch for changes in pagination or filters to reload data
watch([pagination, mergedFilters], () => {
	console.log("pagination or filters changed", pagination.value, mergedFilters.value);
	loadArtifacts();
}, { deep: true });

async function loadArtifacts() {
	isLoading.value = true;

	try {
		const results = await dxArtifact.routes.list({
			...pagination.value,
			filter: mergedFilters.value,
			fields: artifactsField
		} as ListControlsPagination);

		artifacts.value = results.data as Artifact[];
		pagination.value.total = results.meta.total;

		console.log("got results", results);
	} catch (error) {
		console.error("Error loading artifacts:", error);
	} finally {
		isLoading.value = false;
	}
}

// Initial load
loadArtifacts();
</script>
