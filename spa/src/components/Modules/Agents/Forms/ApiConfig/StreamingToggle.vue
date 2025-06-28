<template>
	<div class="streaming-toggle">
		<div class="mb-3 flex items-center gap-2">
			<FaSolidPlay class="w-5 h-5 text-purple-400" />
			<h3 class="text-lg font-semibold text-slate-200">Real-time Streaming</h3>
			<QIcon name="info" class="w-4 h-4 text-slate-400 cursor-help">
				<QTooltip class="bg-slate-800 text-slate-200 shadow-xl max-w-md">
					<div class="p-3">
						<div class="font-semibold mb-2 text-purple-300">Live Response Generation</div>
						<p class="text-sm mb-2">Stream responses as they're generated for better user experience:</p>
						<ul class="text-xs space-y-1">
							<li><strong>Enabled:</strong> Words appear in real-time as the AI thinks</li>
							<li><strong>Disabled:</strong> Complete response delivered all at once</li>
						</ul>
						<p class="text-xs mt-2 text-slate-400">Streaming provides faster perceived response times and better engagement</p>
					</div>
				</QTooltip>
			</QIcon>
		</div>
		
		<div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700">
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-3">
					<FaSolidPlay class="w-4 h-4 text-purple-400" />
					<div>
						<h4 class="font-medium text-slate-200">Enable Streaming</h4>
						<p class="text-sm text-slate-400">
							{{ model ? 'Responses stream word-by-word' : 'Responses delivered when complete' }}
						</p>
					</div>
				</div>
				<QToggle
					v-model="model"
					color="purple"
					size="lg"
				/>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidPlay } from "danx-icon";
import { QIcon, QTooltip, QToggle } from "quasar";

const model = defineModel<boolean>({ required: true });
</script>