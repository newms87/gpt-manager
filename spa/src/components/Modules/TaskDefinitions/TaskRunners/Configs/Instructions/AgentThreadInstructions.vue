<template>
	<div class="mt-8 bg-slate-800 rounded-xl shadow-md p-6">
		<div class="flex-x gap-2 mb-4">
			<span class="text-green-400">🧠</span>
			<h1 class="text-xl font-semibold">Configure an Agent Thread Task</h1>
		</div>

		<p class="mb-4 text-sm text-slate-400">
			This task lets you run an agent (LLM) with instructions and structure to generate smart, useful output based on
			your data. Here's how:
		</p>

		<div class="flex flex-col gap-8 text-slate-400">

			<!-- Step 1 -->
			<div>
				<strong>1. Choose an Agent</strong>:
				<p class="mt-2 text-sm">
					Select which LLM (like OpenAI 4o, Claude, etc.) will run this task.
					<QTooltip>Each model has its own strengths — choose faster, cheaper models for light tasks or more powerful
						ones like GPT-4o for deep analysis.
					</QTooltip>
				</p>

				<div class="mt-3 bg-slate-900 text-cyan-400 rounded p-3 text-xs">
					⚙️ <strong>Example:</strong> Pick <em>OpenAI GPT-4o</em> with <code>temperature = 0.3</code> for creative but
					controlled responses.
				</div>
			</div>

			<!-- Step 2 -->
			<div>
				<strong>2. Add Directives</strong>:
				<p class="mt-2 text-sm">
					Give instructions to the agent before or after it sees the main content.
					<QTooltip>Use <strong>Before Thread</strong> for setup instructions, and <strong>After Thread</strong> for
						follow-up or format-specific guidance.
					</QTooltip>
				</p>

				<div class="grid grid-cols-1 gap-4 mt-3">
					<div class="bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
						<p>📌 <strong>Before Thread Example:</strong> "You are a medical claim expert. Extract details carefully."
						</p>
					</div>
					<div class="bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
						<p>📌 <strong>After Thread Example:</strong> "Respond only in JSON format, following the schema fields."</p>
					</div>
				</div>
			</div>

			<!-- Step 3 -->
			<div>
				<strong>3. Define Output Schema & Fragments</strong>:
				<p class="mt-2 text-sm">
					Choose how the agent should respond: free text or structured data using a <strong>JSON Schema</strong>.
					<QTooltip>If you want to structure the output into clean fields (like a database), use a schema. Otherwise,
						leave it as text.
					</QTooltip>
				</p>

				<div class="mt-3 bg-slate-900 text-sky-400 rounded p-3 text-xs overflow-x-auto">
					{
					"patient_name": "John Doe",
					"diagnosis": "Hypertension",
					"prescriptions": ["Lisinopril", "Amlodipine"]
					}
				</div>

				<p class="mt-4 text-sm">
					You can refine this even further using <strong>Fragments</strong>.
					<QTooltip>Fragments are parts of the schema you want the agent to focus on separately. Each fragment creates
						its own request, letting the agent go deeper on one part at a time.
					</QTooltip>
				</p>

				<div class="grid grid-cols-1 gap-4 mt-3">
					<div class="bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
						<p>📌 <strong>Example 1:</strong> Selecting fragments <code>Referrals</code> and <code>Prescriptions</code>
							will create two separate agent runs.</p>
					</div>
					<div class="bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
						<p>📌 <strong>Example 2:</strong> Use a full schema for one unified response if you don't need to split it.
						</p>
					</div>
				</div>

				<p class="mt-4 text-sm">
					You can also <strong>create or update schemas</strong> right here as needed.
					<QTooltip>Click the pencil ✏️ icon next to a schema or fragment to edit the structure or fields.</QTooltip>
				</p>
			</div>
		</div>
	</div>
</template>
