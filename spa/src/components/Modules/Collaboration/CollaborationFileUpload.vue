<template>
	<div
		ref="dropZoneRef"
		class="border-2 border-dashed rounded-lg p-6 text-center transition-colors cursor-pointer"
		:class="dropZoneClasses"
		@click="openFilePicker"
		@dragenter.prevent="onDragEnter"
		@dragover.prevent="onDragOver"
		@dragleave.prevent="onDragLeave"
		@drop.prevent="onDrop"
	>
		<input
			ref="fileInputRef"
			type="file"
			class="hidden"
			:accept="accept"
			:multiple="multiple"
			@change="onFileInputChange"
		>

		<div v-if="isUploading" class="flex flex-col items-center">
			<QSpinner color="sky" size="lg" class="mb-2" />
			<span class="text-slate-600">Uploading...</span>
		</div>

		<div v-else class="flex flex-col items-center">
			<UploadIcon class="w-8 h-8 text-slate-500 mb-2" />
			<span class="text-slate-700">
				{{ isDragging ? "Drop files here" : "Drag files here or click to browse" }}
			</span>
			<span class="text-xs text-slate-500 mt-1">
				{{ acceptDescription }}
			</span>
		</div>

		<!-- Preview thumbnails for selected images -->
		<div v-if="previewUrls.length > 0" class="flex flex-wrap justify-center gap-2 mt-4">
			<div
				v-for="(url, index) in previewUrls"
				:key="index"
				class="relative w-16 h-16 rounded overflow-hidden bg-slate-100"
			>
				<img
					:src="url"
					alt="Preview"
					class="w-full h-full object-cover"
				>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidCloudArrowUp as UploadIcon } from "danx-icon";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	accept?: string;
	multiple?: boolean;
}>(), {
	accept: "image/*,application/pdf",
	multiple: false
});

const emit = defineEmits<{
	upload: [files: File[]];
}>();

const dropZoneRef = ref<HTMLElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const previewUrls = ref<string[]>([]);

const dropZoneClasses = computed(() => ({
	"border-slate-300 bg-slate-50 hover:border-slate-400": !isDragging.value,
	"border-sky-500 bg-sky-50": isDragging.value
}));

const acceptDescription = computed(() => {
	const types = props.accept.split(",").map(t => t.trim());
	const descriptions: string[] = [];

	for (const type of types) {
		if (type === "image/*") {
			descriptions.push("Images");
		} else if (type === "application/pdf") {
			descriptions.push("PDFs");
		} else if (type.startsWith(".")) {
			descriptions.push(type.toUpperCase());
		} else {
			descriptions.push(type);
		}
	}

	return descriptions.join(", ");
});

/**
 * Open the native file picker
 */
function openFilePicker() {
	fileInputRef.value?.click();
}

/**
 * Handle file input change
 */
function onFileInputChange(event: Event) {
	const input = event.target as HTMLInputElement;
	if (input.files && input.files.length > 0) {
		processFiles(Array.from(input.files));
	}
	// Reset input so same file can be selected again
	input.value = "";
}

/**
 * Handle drag enter
 */
function onDragEnter(event: DragEvent) {
	if (event.dataTransfer?.types.includes("Files")) {
		isDragging.value = true;
	}
}

/**
 * Handle drag over
 */
function onDragOver(event: DragEvent) {
	if (event.dataTransfer?.types.includes("Files")) {
		isDragging.value = true;
	}
}

/**
 * Handle drag leave
 */
function onDragLeave(event: DragEvent) {
	// Only set to false if leaving the drop zone entirely
	const rect = dropZoneRef.value?.getBoundingClientRect();
	if (rect) {
		const { clientX, clientY } = event;
		if (
			clientX < rect.left ||
			clientX > rect.right ||
			clientY < rect.top ||
			clientY > rect.bottom
		) {
			isDragging.value = false;
		}
	}
}

/**
 * Handle drop
 */
function onDrop(event: DragEvent) {
	isDragging.value = false;
	const files = event.dataTransfer?.files;
	if (files && files.length > 0) {
		processFiles(Array.from(files));
	}
}

/**
 * Process selected files and generate previews
 */
function processFiles(files: File[]) {
	// Filter by accept types if needed
	const filteredFiles = filterFilesByAccept(files);

	if (filteredFiles.length === 0) return;

	// Limit to single file if not multiple
	const finalFiles = props.multiple ? filteredFiles : [filteredFiles[0]];

	// Generate preview URLs for images
	generatePreviews(finalFiles);

	// Emit the files
	emit("upload", finalFiles);
}

/**
 * Filter files by accepted types
 */
function filterFilesByAccept(files: File[]): File[] {
	const acceptTypes = props.accept.split(",").map(t => t.trim().toLowerCase());

	return files.filter(file => {
		const fileType = file.type.toLowerCase();
		const extension = "." + file.name.split(".").pop()?.toLowerCase();

		return acceptTypes.some(accept => {
			if (accept === "*/*") return true;
			if (accept.endsWith("/*")) {
				const category = accept.replace("/*", "");
				return fileType.startsWith(category + "/");
			}
			if (accept.startsWith(".")) {
				return extension === accept;
			}
			return fileType === accept;
		});
	});
}

/**
 * Generate preview URLs for image files
 */
function generatePreviews(files: File[]) {
	// Clear old previews
	previewUrls.value.forEach(url => URL.revokeObjectURL(url));
	previewUrls.value = [];

	// Generate new previews for images
	for (const file of files) {
		if (file.type.startsWith("image/")) {
			previewUrls.value.push(URL.createObjectURL(file));
		}
	}
}
</script>
