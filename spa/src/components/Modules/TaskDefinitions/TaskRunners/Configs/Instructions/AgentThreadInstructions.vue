<template>
	<div class="mt-8 bg-slate-800 rounded-xl shadow-lg p-6">
		<div class="flex-x gap-2 mb-6">
			<span class="text-green-400 text-2xl">🧠</span>
			<h1 class="text-2xl font-bold text-white">Configure Agent Thread Task</h1>
			<p class="text-sm text-slate-300">
				Configure the agent and provide directives to guide its behavior. Use Schemas and Fragments if you would like
				JSON structured output.
			</p>
		</div>

		<div class="flex gap-16 justify-between text-slate-200">

			<!-- Step 1: Agent -->
			<div class="flex-1 min-w-[250px]">
				<div class="flex items-center gap-2 mb-2 text-sky-300 font-semibold text-lg">
					<AgentIcon class="w-5 text-sky-500" />
					<span>1. Choose Agent</span>
					<div>
						<HelpIcon class="w-4" />
						<QTooltip class="text-base">
							<div class="max-w-xs">
								Choose the language model that will process your task.
								<ul class="list-disc ml-4 mt-2 text-slate-100">
									<li><strong>OpenAI GPT-4o</strong> is powerful and precise.</li>
									<li><strong>Claude</strong> is great for summarization and reasoning.</li>
									<li><strong>Temperature</strong> controls randomness. Lower is more factual, higher is more creative.
									</li>
								</ul>
							</div>
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
							<div class="max-w-sm">
								<strong>Before Thread</strong> are instructions given to the agent <em>before</em> it sees the content.
								Think of them as reading instructions: what to watch for, how to think, what to extract.
								<br class="my-1" />
								<strong>After Thread</strong> happens <em>after</em> reading—they tell the agent how to respond. You can
								use this to format answers, enforce JSON output, or highlight which fields to include.
							</div>
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>Before:</strong> "You're a medical coder. Read carefully and identify relevant claim details."
					</div>
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>After:</strong> "Return structured JSON per the schema fields: patient, diagnosis, treatments."
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
							<div class="max-w-sm">
								Choose how the agent should return its answer:
								<ul class="list-disc ml-4 mt-1 text-slate-100">
									<li><strong>Text:</strong> Freeform answer in the agent's own words.</li>
									<li><strong>JSON Schema:</strong> Structured response, easy to parse and reuse.</li>
								</ul>
								<p class="mt-2">You can edit or create schemas to match exactly what fields you need.</p>
							</div>
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
							<div class="max-w-sm">
								Use fragments to split your schema into smaller, focused groups of data points.
								<ul class="list-disc ml-4 mt-2 text-slate-100">
									<li>Each fragment is run as its own agent task.</li>
									<li>Helps the agent stay focused and more accurate.</li>
									<li>Ideal fragment size: <strong>3 to 8 related fields</strong>.</li>
								</ul>
								<p class="mt-2">Encouraged: break large schemas into multiple fragments to improve clarity and control
									prompt behavior for each part.</p>
							</div>
						</QTooltip>
					</div>
				</div>

				<div class="text-sm space-y-2">
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>Example:</strong> Select <code>Referrals</code> and <code>Prescriptions</code> to get focused
						results.
					</div>
					<div class="bg-sky-900 border-l-4 border-sky-600 p-2 rounded">
						📌 <strong>Pro Tip:</strong> Smaller, focused fragments = better output and more reusable results.
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
