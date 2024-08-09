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
				<div
					v-for="result in searchResults"
					:key="result.product + result.injury"
					class="bg-slate-800 rounded-lg shadow-lg px-4 py-2 m-2 flex items-center flex-nowrap"
				>
					<div class="flex-grow">
						<div class="text-lg font-bold text-sky-300 flex items-center">
							<a :href="result.sources[0].url" target="_blank">{{ result.product }}: {{ result.injury }}</a>
							<div class="text-sm text-slate-400 ml-3">by {{ result.company }}</div>
						</div>
						<div class="mt-2">
							{{ result.description }}
						</div>
					</div>
					<div>
						<QBtn class="bg-lime-900 px-4" :loading="isStartingResearch === result" @click="onResearch(result)">
							<BotIcon class="w-5 mr-3" />
							Research
						</QBtn>
					</div>
				</div>
			</ListTransition>
		</div>
	</div>
</template>
<script setup lang="ts">
import { AiSearchRoutes } from "@/routes/searchRoutes";
import { ResearchResult, SearchItem, SearchResult } from "@/types/research";
import { FaSolidRobot as BotIcon, FaSolidWandSparkles as SearchIcon, FaSolidXmark as ClearIcon } from "danx-icon";
import { FlashMessages, ListTransition, TextField } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits(["refresh"]);
const searchText = ref("");
const searchResults = ref<SearchItem[]>([]);
const isSearching = ref(false);
const isStartingResearch = ref<SearchItem | null>(null);

async function onSearch() {
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

async function onResearch(result: SearchItem) {
	isStartingResearch.value = result;
	const response: ResearchResult = await AiSearchRoutes.research(result);
	isStartingResearch.value = null;

	if (response.success) {
		emit("refresh");
		FlashMessages.info(`Researching ${result.product}: ${result.injury} in workflow ${response.workflowRun.id}`);
		searchResults.value = searchResults.value.filter((r) => r !== result);
	} else {
		FlashMessages.error(response.message || "Failed to start researching agent. Try again later...");
	}
}

function onClear() {
	searchText.value = "";
	searchResults.value = [];
}
</script>
