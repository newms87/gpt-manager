<template>
	<div class="absolute right-0 top-0 bottom-0 flex z-10">
		<!-- Toggle Buttons (positioned to left of sidebar) -->
		<div class="py-3 pr-2">
			<FragmentSelectorControlPanel
				:modes="props.modes"
				:artifacts-enabled="artifactsEnabled"
				:artifacts-visible="artifactsVisible"
				:artifact-count="artifactCount"
				@toggle-artifacts="emit('toggle-artifacts')"
			/>
		</div>

		<!-- Code Sidebar -->
		<div class="w-80 flex flex-col bg-slate-800/95 border-l border-slate-600 overflow-hidden">
			<div class="flex items-center justify-between px-4 py-3 bg-slate-700/90 border-b border-slate-600">
				<span class="text-sm font-medium text-slate-200">
					{{ props.modes.effectiveSelectionEnabled.value ? 'Selection' : 'Schema' }}
				</span>
				<div class="flex items-center gap-3 text-xs text-slate-400">
					<span>Models: {{ props.counts.models }}</span>
					<span>Props: {{ props.counts.properties }}</span>
				</div>
			</div>
			<div class="flex-1 min-h-0 overflow-auto">
				<CodeViewer
					:model-value="props.data"
					editor-class="p-3"
					hide-footer
				/>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import FragmentSelectorControlPanel from "./FragmentSelectorControlPanel.vue";
import { FragmentSelectorModesResult } from "./useFragmentSelectorModes";
import { FragmentSelector, JsonSchema } from "@/types";
import { CodeViewer } from "quasar-ui-danx";

const props = defineProps<{
	modes: FragmentSelectorModesResult;
	data: FragmentSelector | JsonSchema | null;
	counts: { models: number; properties: number };
	artifactsEnabled?: boolean;
	artifactsVisible?: boolean;
	artifactCount?: number;
}>();

const emit = defineEmits<{
	"toggle-artifacts": [];
}>();
</script>
