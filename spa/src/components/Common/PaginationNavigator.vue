<template>
	<div class="flex flex-col sm:flex-row items-center gap-4">
		<!-- Page size selector -->
		<div class="flex items-center gap-2">
			<label for="page-size" class="text-sm text-gray-600">Items per page:</label>
			<SelectField
				:model-value="pagination.perPage"
				:options="pageSizeOptions"
				select-class="dx-select-field-dense"
				@update:model-value="perPage => pagination = {...pagination, perPage}"
			/>
		</div>

		<!-- Pagination controls -->
		<div class="flex items-center gap-1">
			<!-- First page -->
			<button
				:disabled="currentPage <= 1"
				class="flex items-center justify-center p-1 rounded hover:bg-sky-700 hover:text-white disabled:opacity-50 disabled:pointer-events-none"
				aria-label="Go to first page"
				@click="goToPage(1)"
			>
        <span class="w-5 h-5 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
            <path
							fill-rule="evenodd"
							d="M15.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 010 1.414zm-6 0a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 011.414 1.414L5.414 10l4.293 4.293a1 1 0 010 1.414z"
							clip-rule="evenodd"
						/>
          </svg>
        </span>
			</button>

			<!-- Previous page -->
			<button
				:disabled="currentPage <= 1"
				class="flex items-center justify-center p-1 rounded hover:bg-sky-700 hover:text-white disabled:opacity-50 disabled:pointer-events-none"
				aria-label="Go to previous page"
				@click="goToPage(currentPage - 1)"
			>
        <span class="w-5 h-5 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
            <path
							fill-rule="evenodd"
							d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
							clip-rule="evenodd"
						/>
          </svg>
        </span>
			</button>

			<!-- Page numbers -->
			<div class="flex items-center">
				<template v-if="totalPages <= 7">
					<button
						v-for="pageNum in totalPages"
						:key="pageNum"
						:class="[
              'w-8 h-8 flex items-center justify-center rounded text-sm',
              currentPage === pageNum ? 'bg-sky-900 text-white' : 'hover:bg-sky-700 hover:text-white'
            ]"
						:aria-label="`Go to page ${pageNum}`"
						:aria-current="currentPage === pageNum ? 'page' : undefined"
						@click="goToPage(pageNum)"
					>
						{{ pageNum }}
					</button>
				</template>

				<template v-else>
					<!-- First group -->
					<template v-if="currentPage <= 4">
						<button
							v-for="pageNum in 5"
							:key="pageNum"
							:class="[
                'w-8 h-8 flex items-center justify-center rounded text-sm',
                currentPage === pageNum ? 'bg-sky-900 text-white' : 'hover:bg-sky-700 hover:text-white'
              ]"
							:aria-current="currentPage === pageNum ? 'page' : undefined"
							@click="goToPage(pageNum)"
						>
							{{ pageNum }}
						</button>
						<span class="w-8 h-8 flex items-center justify-center">...</span>
						<button
							class="w-8 h-8 flex items-center justify-center rounded text-sm hover:bg-sky-700 hover:text-white"
							:aria-label="`Go to page ${totalPages}`"
							@click="goToPage(totalPages)"
						>
							{{ totalPages }}
						</button>
					</template>

					<!-- Middle group -->
					<template v-else-if="currentPage > 4 && currentPage < totalPages - 3">
						<button
							class="w-8 h-8 flex items-center justify-center rounded text-sm hover:bg-sky-700 hover:text-white"
							aria-label="Go to page 1"
							@click="goToPage(1)"
						>
							1
						</button>
						<span class="w-8 h-8 flex items-center justify-center">...</span>
						<button
							v-for="pageNum in [currentPage - 1, currentPage, currentPage + 1]"
							:key="pageNum"
							:class="[
                'w-8 h-8 flex items-center justify-center rounded text-sm',
                currentPage === pageNum ? 'bg-sky-900 text-white' : 'hover:bg-sky-700 hover:text-white'
              ]"
							:aria-current="currentPage === pageNum ? 'page' : undefined"
							@click="goToPage(pageNum)"
						>
							{{ pageNum }}
						</button>
						<span class="w-8 h-8 flex items-center justify-center">...</span>
						<button
							class="w-8 h-8 flex items-center justify-center rounded text-sm hover:bg-sky-700 hover:text-white"
							:aria-label="`Go to page ${totalPages}`"
							@click="goToPage(totalPages)"
						>
							{{ totalPages }}
						</button>
					</template>

					<!-- Last group -->
					<template v-else>
						<button
							class="w-8 h-8 flex items-center justify-center rounded text-sm hover:bg-sky-700 hover:text-white"
							aria-label="Go to page 1"
							@click="goToPage(1)"
						>
							1
						</button>
						<span class="w-8 h-8 flex items-center justify-center">...</span>
						<button
							v-for="pageNum in 5"
							:key="totalPages - 5 + pageNum"
							:class="[
                'w-8 h-8 flex items-center justify-center rounded text-sm',
                currentPage === totalPages - 5 + pageNum ? 'bg-sky-900 text-white' : 'hover:bg-sky-700 hover:text-white'
              ]"
							:aria-current="currentPage === totalPages - 5 + pageNum ? 'page' : undefined"
							@click="goToPage(totalPages - 5 + pageNum)"
						>
							{{ totalPages - 5 + pageNum }}
						</button>
					</template>
				</template>
			</div>

			<!-- Next page -->
			<button
				:disabled="currentPage >= totalPages"
				class="flex items-center justify-center p-1 rounded hover:bg-sky-700 hover:text-white disabled:opacity-50 disabled:pointer-events-none"
				aria-label="Go to next page"
				@click="goToPage(currentPage + 1)"
			>
        <span class="w-5 h-5 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
            <path
							fill-rule="evenodd"
							d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
							clip-rule="evenodd"
						/>
          </svg>
        </span>
			</button>

			<!-- Last page -->
			<button
				:disabled="currentPage >= totalPages"
				class="flex items-center justify-center p-1 rounded hover:bg-sky-700 hover:text-white disabled:opacity-50 disabled:pointer-events-none"
				aria-label="Go to last page"
				@click="goToPage(totalPages)"
			>
        <span class="w-5 h-5 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
            <path
							fill-rule="evenodd"
							d="M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z"
							clip-rule="evenodd"
						/>
            <path
							fill-rule="evenodd"
							d="M4.293 15.707a1 1 0 010-1.414L8.586 10 4.293 5.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z"
							clip-rule="evenodd"
						/>
          </svg>
        </span>
			</button>
		</div>

		<!-- Page number input -->
		<div class="flex items-center gap-2">
			<span class="text-sm text-gray-600">Go to page:</span>
			<input
				v-model.number="manualPage"
				type="number"
				min="1"
				:max="totalPages"
				class="w-16 p-1 rounded border border-gray-300 bg-white text-gray-700 text-sm"
				aria-label="Enter page number"
				@input="debouncedHandleManualPageInput"
			/>
		</div>

		<!-- Page info -->
		<div class="text-sm text-gray-600 ml-auto">
			<span>{{ pageRangeStart }}-{{ pageRangeEnd }} of {{ pagination.total }} items</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { PaginationModel } from "@/types/Pagination";
import { useDebounceFn } from "@vueuse/core";
import { SelectField } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	pageSizes?: number[];
}>(), {
	pageSizes: () => [10, 20, 50, 100]
});

const pagination = defineModel<PaginationModel>();

type PaginationEmits = {
	"update:page": [value: number]
	"update:perPage": [value: number]
}

const emit = defineEmits<PaginationEmits>();
const pageSizeOptions = computed(() => {
	return props.pageSizes.map(size => ({ label: size.toString(), value: size }));
});

// Computed properties for UI
const currentPage = computed(() => Math.max(pagination.value.page || 1, 1)); // Ensure we have a valid page number greater than 0
const totalPages = computed(() => Math.max(Math.ceil((pagination.value.total || 0) / pagination.value.perPage) || 1, 1)); // Ensure we have at least 1 page

const pageRangeStart = computed(() => {
	if (pagination.value.total === 0) return 0;
	return ((currentPage.value - 1) * pagination.value.perPage) + 1;
});

const pageRangeEnd = computed(() => {
	const end = currentPage.value * pagination.value.perPage;
	return end > pagination.value.total ? pagination.value.total : end;
});

// Manual page input
const manualPage = ref(currentPage.value);

// Watch for external page changes to update the input field
watch(() => pagination.value.page, (newPage) => {
	manualPage.value = Math.max(newPage || 1, 1);
});

// Methods
function goToPage(page: number) {
	if (page < 1 || page > totalPages.value) return;
	emit("update:page", page);
}

// Debounced handler for manual page input
const debouncedHandleManualPageInput = useDebounceFn(() => {
	let page = manualPage.value;

	// Validate the input
	if (!page || isNaN(page)) {
		manualPage.value = currentPage.value;
		return;
	}

	// Ensure page is within valid range
	if (page < 1) page = 1;
	if (page > totalPages.value) page = totalPages.value;

	if (page !== pagination.value.page) {
		emit("update:page", page);
	}

	manualPage.value = page;
}, 500); // 500ms debounce
</script>
