<template>
	<div class="p-4">
		<!-- Active thread -->
		<div v-if="activeThread" class="space-y-4">
			<div class="flex items-center justify-between">
				<div>
					<h4 class="text-sm font-semibold text-slate-200">Active Collaboration</h4>
					<p class="text-xs text-slate-400">{{ activeThread.name || "Unnamed thread" }}</p>
				</div>
				<ActionButton
					type="view"
					label="Open Builder"
					color="sky"
					size="sm"
					@click="openBuilder"
				/>
			</div>

			<div class="bg-slate-700 rounded p-3">
				<div class="flex items-center text-sm">
					<span class="text-slate-400">Messages:</span>
					<span class="text-slate-200 ml-2">{{ activeThread.messages?.length || 0 }}</span>
				</div>
				<div v-if="activeThread.is_running" class="flex items-center mt-2 text-sky-400">
					<QSpinner size="xs" class="mr-2" />
					<span class="text-xs">Thread is running...</span>
				</div>
			</div>
		</div>

		<!-- No active thread -->
		<div v-else class="text-center py-8">
			<ChatIcon class="w-12 h-12 mx-auto text-slate-600 mb-4" />
			<p class="text-slate-400 mb-4">No active collaboration thread</p>
			<ActionButton
				type="play"
				label="Start New Collaboration"
				color="sky-invert"
				@click="openBuilder"
			/>
		</div>

		<!-- Previous threads -->
		<div v-if="previousThreads.length > 0" class="mt-6">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Previous Collaborations</h4>
			<div class="space-y-2">
				<div
					v-for="thread in previousThreads"
					:key="thread.id"
					class="bg-slate-700 rounded p-3 flex items-center justify-between"
				>
					<div>
						<div class="text-sm text-slate-200">{{ thread.name || "Unnamed thread" }}</div>
						<div class="text-xs text-slate-500">{{ fDateTime(thread.timestamp) }}</div>
					</div>
					<ActionButton
						type="view"
						size="xs"
						color="slate"
						tooltip="View thread"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import type { TemplateDefinition } from "@/ui/templates/types";
import { AgentThread } from "@/types";
import { FaSolidComments as ChatIcon } from "danx-icon";
import { ActionButton, fDateTime } from "quasar-ui-danx";
import { computed } from "vue";
import { useRouter } from "vue-router";

const props = defineProps<{
	template: TemplateDefinition;
}>();

const router = useRouter();

/**
 * Get the most recent active thread
 */
const activeThread = computed<AgentThread | null>(() => {
	const threads = props.template.collaboration_threads || [];
	// Find the most recent thread (could also check for running threads first)
	return threads.length > 0 ? threads[0] : null;
});

/**
 * Get previous threads (excluding active)
 */
const previousThreads = computed<AgentThread[]>(() => {
	const threads = props.template.collaboration_threads || [];
	return threads.slice(1);
});

/**
 * Navigate to template builder
 */
function openBuilder() {
	router.push({ name: "template-builder", params: { id: props.template.id } });
}
</script>
