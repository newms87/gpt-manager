<template>
	<div ref="triggerRef" class="relative inline-block">
		<slot name="trigger" :toggle="toggle" />

		<Teleport to="body">
			<Transition
				name="popover"
				enter-active-class="transition duration-200 ease-out"
				enter-from-class="transform scale-95 opacity-0"
				enter-to-class="transform scale-100 opacity-100"
				leave-active-class="transition duration-150 ease-in"
				leave-from-class="transform scale-100 opacity-100"
				leave-to-class="transform scale-95 opacity-0"
			>
				<div
					v-if="isShowing"
					ref="contentRef"
					:class="['popover-content absolute shadow-lg', positionClass]"
					:style="contentStyle"
					@mousedown.stop
				>
					<slot />
				</div>
			</Transition>
		</Teleport>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	placement?: "top" | "right" | "bottom" | "left" | "auto";
	offset?: number;
	closeOnClickOutside?: boolean;
	autoClose?: boolean;
	anchorWidth?: boolean;
}>(), {
	placement: "bottom",
	offset: 8
});

const isShowing = defineModel<boolean>();

// References
const triggerRef = ref<HTMLElement | null>(null);
const contentRef = ref<HTMLElement | null>(null);

// Position state
const contentPosition = ref({
	top: 0,
	left: 0,
	width: 0
});

// Computed properties
const positionClass = computed(() => {
	const placement = currentPlacement.value;
	if (placement === "top") return "origin-bottom";
	if (placement === "right") return "origin-left";
	if (placement === "bottom") return "origin-top";
	if (placement === "left") return "origin-right";
	return "";
});

const contentStyle = computed(() => {
	return {
		top: `${contentPosition.value.top}px`,
		left: `${contentPosition.value.left}px`,
		width: props.anchorWidth ? `${contentPosition.value.width}px` : undefined
	};
});

// Reactive current placement that may adapt based on available space
const currentPlacement = ref(props.placement);

watch(() => isShowing.value, async (isOpen) => {
	if (isOpen) {
		// Reset placement to prop value when opening
		currentPlacement.value = props.placement;
		// Wait for the content to render
		await nextTick();
		// Calculate position
		updatePosition();
	}
});

// Public methods
function toggle() {
	isShowing.value = !isShowing.value;
}

function close() {
	isShowing.value = false;
}

function updatePosition() {
	if (!triggerRef.value || !contentRef.value || !isShowing.value) return;

	const triggerRect = triggerRef.value.getBoundingClientRect();
	const contentRect = contentRef.value.getBoundingClientRect();
	const viewportWidth = window.innerWidth;
	const viewportHeight = window.innerHeight;

	// Set width if anchorWidth is true
	contentPosition.value.width = triggerRect.width;

	// Start with requested placement
	let placement = props.placement;

	// Auto placement - determine the best position based on available space
	if (placement === "auto") {
		const spaceBelow = viewportHeight - triggerRect.bottom;
		const spaceAbove = triggerRect.top;
		const spaceRight = viewportWidth - triggerRect.right;
		const spaceLeft = triggerRect.left;

		// Determine which side has the most space
		const spaces = [
			{ placement: "bottom", space: spaceBelow },
			{ placement: "top", space: spaceAbove },
			{ placement: "right", space: spaceRight },
			{ placement: "left", space: spaceLeft }
		];

		// Sort by space available
		spaces.sort((a, b) => b.space - a.space);
		placement = spaces[0].placement as "top" | "right" | "bottom" | "left";
	}

	// Check if the content would be cut off in current placement
	// and override if necessary
	if (placement === "bottom" && triggerRect.bottom + contentRect.height + props.offset > viewportHeight) {
		placement = "top";
	}
	if (placement === "top" && triggerRect.top - contentRect.height - props.offset < 0) {
		placement = "bottom";
	}
	if (placement === "right" && triggerRect.right + contentRect.width + props.offset > viewportWidth) {
		placement = "left";
	}
	if (placement === "left" && triggerRect.left - contentRect.width - props.offset < 0) {
		placement = "right";
	}

	// Store the final placement
	currentPlacement.value = placement;

	// Calculate position based on final placement
	let top = 0;
	let left = 0;

	switch (placement) {
		case "top":
			top = triggerRect.top - contentRect.height - props.offset + window.scrollY;
			left = triggerRect.left + (triggerRect.width - contentRect.width) / 2 + window.scrollX;
			break;
		case "right":
			top = triggerRect.top + (triggerRect.height - contentRect.height) / 2 + window.scrollY;
			left = triggerRect.right + props.offset + window.scrollX;
			break;
		case "bottom":
			top = triggerRect.bottom + props.offset + window.scrollY;
			left = triggerRect.left + (triggerRect.width - contentRect.width) / 2 + window.scrollX;
			break;
		case "left":
			top = triggerRect.top + (triggerRect.height - contentRect.height) / 2 + window.scrollY;
			left = triggerRect.left - contentRect.width - props.offset + window.scrollX;
			break;
	}

	// Ensure the popover stays within viewport horizontally
	if (left < 0) {
		left = 8; // Add some padding from the edge
	} else if (left + contentRect.width > viewportWidth) {
		left = viewportWidth - contentRect.width - 8;
	}

	// Update the position
	contentPosition.value = {
		top,
		left,
		width: contentPosition.value.width
	};
}

// Handling click outside
function handleClickOutside(event: MouseEvent) {
	if (
		props.closeOnClickOutside &&
		isShowing.value &&
		triggerRef.value &&
		contentRef.value &&
		!triggerRef.value.contains(event.target as Node) &&
		!contentRef.value.contains(event.target as Node)
	) {
		close();
		event.preventDefault();
	}
}

function handleEscapeKey(event: KeyboardEvent) {
	if (event.key === "Escape" && isShowing.value) {
		close();
	}
}

// Auto-close handling for click inside popover
function handlePopoverClick() {
	if (props.autoClose) {
		close();
	}
}

onMounted(() => {
	document.addEventListener("click", handleClickOutside, true);
	document.addEventListener("keydown", handleEscapeKey);

	if (contentRef.value && props.autoClose) {
		contentRef.value.addEventListener("click", handlePopoverClick);
	}

	// Watch for window resize to update position
	window.addEventListener("resize", updatePosition);
});

onUnmounted(() => {
	document.removeEventListener("click", handleClickOutside, true);
	document.removeEventListener("keydown", handleEscapeKey);

	if (contentRef.value && props.autoClose) {
		contentRef.value.removeEventListener("click", handlePopoverClick);
	}
});

// Expose toggle and close methods
defineExpose({ toggle, close, updatePosition });
</script>

<style scoped lang="scss">
.popover-content {
	max-width: calc(100vw - 16px);
	max-height: calc(100vh - 16px);
	overflow: auto;
	z-index: 6000 !important; /* Ensure popover appears above all other elements */
}
</style>
