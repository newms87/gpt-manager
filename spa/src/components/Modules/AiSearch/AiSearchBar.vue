<template>
	<div class="flex items-center flex-nowrap">
		<TextField
			v-model="searchText"
			class="large-white flex-grow rounded-full overflow-hidden"
			input-class="text-xl h-14 rounded-full"
			placeholder="AI Search..."
			:debounce="500"
			:disabled="isSearching"
			@submit="onSearch"
		>
			<template #prepend>
				<div class="bg-purple-500 p-3 rounded-full ml-2 hover:bg-purple-900 transition-all cursor-pointer">
					<SearchIcon class="w-4 text-yellow-200" />
				</div>
			</template>
			<template #append>
				<div>
					<ClearIcon
						v-if="searchText"
						class="w-6 text-slate-500 mr-5 hover:text-slate-800 transition-all cursor-pointer"
						@click="onClear"
					/>
				</div>
			</template>
		</TextField>

		<div class="search-results absolute-bottom mb-20 mx-12 bg-slate-700 rounded-lg">
			<ListTransition>
				<div v-if="isSearching" key="searching" class="flex items-center flex-nowrap p-4">
					<QSpinnerOrbit size="32" />
					<div class="ml-4">
						Searching for {{ searchText }}...
					</div>
				</div>
				<div v-else-if="drugSideEffect" class="p-4">
					Ask me anything about {{ drugSideEffect.product.companies.map(c => c.name).join(", ") }} or {{
						drugSideEffect.product.name
					}} and
					I will be happy to answer!
				</div>
				<AiSearchResult
					v-for="result in searchResults"
					:key="result.product_name"
					class="m-2"
					:result="result"
					@research="onResearch"
				/>
			</ListTransition>
		</div>
	</div>
</template>
<script setup lang="ts">
import AiSearchResult from "@/components/Modules/AiSearch/AiSearchResult";
import { DrugSideEffect } from "@/components/Modules/Tortguard/tortguard";
import { AiSearchRoutes } from "@/routes/searchRoutes";
import { SearchResult, SearchResultItem, SearchResultItemBySideEffect } from "@/types/research";
import { FaSolidWandSparkles as SearchIcon, FaSolidXmark as ClearIcon } from "danx-icon";
import { FlashMessages, ListTransition, TextField } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits(["refresh"]);
const props = defineProps<{ drugSideEffect: DrugSideEffect | null }>();
const searchText = ref("");
const searchResults = ref<SearchResultItem[]>([]);
const isSearching = ref(false);

async function onSearch() {
	if (!searchText.value) return;

	if (props.drugSideEffect) {
		searchText.value = "";
		FlashMessages.warning("This feature is coming soon!");
		return;
	}

	isSearching.value = true;
	const response: SearchResult = await AiSearchRoutes.search(searchText.value);
	isSearching.value = false;

	if (response.success) {
		searchResults.value = response.results;
	} else {
		searchResults.value = [];
		FlashMessages.error(response.message || "Failed to search. Try another search query...");
	}
}

async function onResearch(result: SearchResultItemBySideEffect) {
	searchResults.value = searchResults.value.filter((r) => r.product_name !== result.product_name);
	emit("refresh");
}

function onClear() {
	searchText.value = "";
	searchResults.value = [];
}
</script>
