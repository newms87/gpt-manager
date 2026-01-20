<template>
    <div class="relative flex flex-col overflow-hidden">
        <div class="flex-x gap-2 mb-4">
            <div class="flex-grow text-lg" :class="themeClass('text-slate-200', 'text-slate-800')">Child Artifacts</div>
            <SearchBox
                v-model="searchText"
                class="w-96"
                placeholder="Search artifacts..."
            />
            <ArtifactFilterButton v-model="filters" />
        </div>
        <div class="flex-grow overflow-hidden">
            <template v-if="!artifacts.length && isLoading">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <QSkeleton v-for="i in pagination.perPage" :key="i" class="h-48 rounded-lg" />
                </div>
            </template>
            <div v-else-if="artifacts.length === 0" class="text-xl text-center text-gray-500">No Child Artifacts</div>
            <div v-else class="relative h-full overflow-y-auto">
                <LoadingOverlay v-if="isLoading && artifacts.length > 0" />

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <ArtifactCard
                        v-for="artifact in artifacts"
                        :key="artifact.id"
                        :artifact="artifact"
                        @open-dialog="(art, tab) => emit('navigate', art, tab)"
                    />
                </div>
            </div>
        </div>

        <PaginationNavigator
            v-if="pagination.total > 10"
            v-model="pagination"
            class="bg-sky-950 text-slate-400 py-2 mt-4 px-3 shadow-lg rounded-lg"
            remember-key="child-artifact-list"
        />
    </div>
</template>

<script setup lang="ts">
import ArtifactCard from "@/components/Modules/Artifacts/ArtifactCard.vue";
import ArtifactFilterButton from "@/components/Modules/Artifacts/ArtifactFilterButton.vue";
import { dxArtifact } from "@/components/Modules/Artifacts/config";
import { LoadingOverlay, PaginationNavigator, SearchBox } from "@/components/Shared";
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import { Artifact } from "@/types";
import { PaginationModel } from "@/types/Pagination";
import { AnyObject, ListControlsPagination } from "quasar-ui-danx";
import { QSkeleton } from "quasar";
import { computed, onMounted, ref, shallowRef, watch } from "vue";

const props = defineProps<{
    parentArtifactId: number;
}>();

type ContentTab = "text" | "files" | "json" | "meta" | "children";

const emit = defineEmits<{
    navigate: [artifact: Artifact, tab: ContentTab];
}>();

const { themeClass } = useAuditCardTheme();

// Artifacts data
const artifacts = shallowRef<Artifact[]>([]);
const isLoading = ref(false);

// Pagination state
const pagination = ref<PaginationModel>({
    page: 1,
    perPage: 10,
    total: 0
});

// Search text and filter state
const searchText = ref("");
const filters = ref<AnyObject>({});

// Merge parent filter with filters from filter component
const mergedFilters = computed(() => {
    return { parent_artifact_id: props.parentArtifactId, ...filters.value, keywords: searchText.value };
});

// Requested fields for artifacts
const artifactsField = {
    text_content: true,
    json_content: true,
    meta: true,
    files: { thumb: true }
};

// Watch for changes in pagination or filters to reload data
watch(mergedFilters, (value, oldValue) => {
    if (JSON.stringify(value) !== JSON.stringify(oldValue)) {
        pagination.value.page = 1;
        loadArtifacts();
    }
});
watch(pagination, loadArtifacts);

async function loadArtifacts() {
    isLoading.value = true;

    const results = await dxArtifact.routes.list({
        ...pagination.value,
        filter: mergedFilters.value,
        fields: artifactsField
    } as ListControlsPagination);

    // Ignore bad responses (probably an abort or network connection issue)
    if (!results.data) return;

    artifacts.value = results.data as Artifact[];
    pagination.value.total = results.meta.total || 0;
    isLoading.value = false;
}

// Initial load
onMounted(loadArtifacts);
</script>
