<template>
	<div class="bg-slate-800/50 rounded-lg p-4 border border-slate-700 hover:border-slate-600 transition-colors">
		<div class="flex-x gap-2 mb-1">
			<ServerIcon class="w-4 text-blue-400" />
			<div class="font-medium text-slate-200">{{ mcpServer.name }}</div>
		</div>
		
		<div class="text-sm text-slate-400">{{ mcpServer.server_url }}</div>
		
		<div v-if="mcpServer.description" class="text-xs text-slate-500 mt-2">
			{{ mcpServer.description }}
		</div>

		<div v-if="mcpServer.allowed_tools?.length" class="flex flex-wrap gap-1 mt-3">
			<LabelPillWidget
				v-for="tool in mcpServer.allowed_tools"
				:key="tool"
				:label="tool"
				color="slate"
				size="xs"
			/>
		</div>

		<div v-if="showActions || showSelect" class="flex-x justify-between gap-2 mt-3 pt-3 border-t border-slate-700">
			<div v-if="showSelect">
				<ActionButton
					v-if="!isSelected"
					color="blue"
					size="sm"
					@click="$emit('select', mcpServer)"
				>
					Select
				</ActionButton>
				<ActionButton
					v-else
					color="green"
					size="sm"
					disabled
				>
					Selected
				</ActionButton>
			</div>
			<div v-if="showActions" class="flex-x gap-2">
				<ActionButton
					type="edit"
					color="slate"
					size="sm"
					@click="$emit('edit', mcpServer)"
				/>
				<ActionButton
					type="trash"
					color="red"
					size="sm"
					@click="$emit('delete', mcpServer)"
				/>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { McpServer } from "@/types";
import { FaSolidServer as ServerIcon } from "danx-icon";
import { ActionButton, LabelPillWidget } from "quasar-ui-danx";

defineProps<{
	mcpServer: McpServer;
	showActions?: boolean;
	showSelect?: boolean;
	isSelected?: boolean;
}>();

defineEmits<{
	edit: [mcpServer: McpServer];
	delete: [mcpServer: McpServer];
	select: [mcpServer: McpServer];
}>();
</script>