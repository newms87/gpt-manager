<template>
	<div class="mt-8 bg-slate-800 rounded-xl shadow-lg p-6">
		<div class="flex gap-2 items-center mb-6">
			<span class="text-green-400 text-2xl">🧠</span>
			<h1 class="text-2xl font-bold text-white">Configure Agent Thread Task</h1>
		</div>

		<p class="mb-6 text-sm text-slate-300">
			Guide the agent to produce smart output using structured steps. Everything flows from left to right — just follow
			the path!
		</p>

		<div class="flex gap-10 justify-between text-slate-200">

			<!-- Step 1: Agent -->
			<div class="flex-1 min-w-[250px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<AgentIcon class="w-5 text-sky-500" />
					<span>1. Choose Agent</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip>
							Pick which LLM (like OpenAI 4o or Claude) will do the work. Set a temperature for creativity vs precision.
						</QTooltip>
					</div>
				</div>

				<p class="text-sm text-slate-400 mb-2">
					🧪 <strong>Example:</strong> Use GPT-4o + temperature <code>0.3</code> for analytical tasks.
				</p>
			</div>

			<!-- Step 2: Directives -->
			<div class="flex-1 min-w-[250px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<DirectiveIcon class="w-5 text-sky-500" />
					<span>2. Add Directives</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							Use "Before" for instructions before the agent sees data. Use "After" for formatting or follow-ups.
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>Before:</strong> "You're a legal assistant. Analyze and summarize the claim."
					</div>
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>After:</strong> "Respond using JSON format per the schema."
					</div>
				</div>
			</div>

			<!-- Step 3: Schema + Fragments -->
			<div class="flex-1 min-w-[250px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<SchemaIcon class="w-5 text-sky-500" />
					<span>3. Output Schema</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							Choose how the agent should reply: free text or a structured JSON format. You can also edit or create
							schemas.
						</QTooltip>
					</div>
				</div>

				<div class="bg-slate-900 text-sky-400 rounded p-3 text-xs overflow-x-auto mb-3">
					{
					"patient_name": "John Doe",
					"diagnosis": "Hypertension",
					"prescriptions": ["Lisinopril"]
					}
				</div>

				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<FragmentIcon class="w-5 text-sky-500" />
					<span>Fragments</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							Choose 1+ schema fields for the agent to focus on. Each fragment = separate agent task. Results can be
							merged later.
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>Example:</strong> Select <code>Referrals</code> and <code>Prescriptions</code> to get focused
						results.
					</div>
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>Pro Tip:</strong> One fragment per agent = more accurate and clean results.
					</div>
				</div>
			</div>

		</div>
	</div>
</template>
<script setup lang="ts">
import {
	FaSolidCircleQuestion as HelpIcon,
	FaSolidDatabase as SchemaIcon,
	FaSolidFile as DirectiveIcon,
	FaSolidPuzzlePiece as FragmentIcon,
	FaSolidRobot as AgentIcon
} from "danx-icon";
</script>
