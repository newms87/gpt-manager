<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="mt-8 bg-slate-800 rounded-xl shadow-md p-6">
			<div class="flex-x gap-2 mb-4">
				<span class="text-blue-600">⚙️</span>
				<h1 class="text-xl font-semibold">Define Schema and Fragment</h1>
			</div>

			<p class="mb-4 text-sm text-slate-500">
				To group JSON content by a shared key:
			</p>

			<div class="flex items-stretch gap-8 text-slate-400">
				<div>
					<strong>1. Select a Schema</strong>: Defines the data structure.
					<pre class="bg-slate-900 text-sky-600 rounded p-3 text-xs mt-2 overflow-x-auto">{
  "id": "prod-001",
  "name": "Wireless Mouse",
  "category": "Electronics",
  "brand": "LogiTech",
  "price": 39.99
}</pre>
				</div>
				<div>
					<strong>2. Select a Fragment</strong>: Choose the field to group by (e.g.,
					<code class="bg-sky-700 px-2 py-1 rounded">category</code>,
					<code class="bg-sky-700 px-2 py-1 rounded">brand</code>,
					<code class="bg-sky-700 px-2 py-1 rounded">price</code>).

					<div class="mt-4 bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
						<p>📌 <strong>Example:</strong> Grouping by <code class="bg-sky-700 px-2 py-1 rounded">brand</code> puts
							all
							"LogiTech" products in one set, "Sony" in another.</p>
					</div>
				</div>
			</div>
		</div>
		<SchemaAndFragmentsConfig :task-definition="taskDefinition" class="p-4" :max-fragments="1" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import SchemaAndFragmentsConfig
	from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/SchemaAndFragmentsConfig";
import { TaskDefinition } from "@/types";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

defineProps<{
	taskDefinition: TaskDefinition;
}>();
</script>
