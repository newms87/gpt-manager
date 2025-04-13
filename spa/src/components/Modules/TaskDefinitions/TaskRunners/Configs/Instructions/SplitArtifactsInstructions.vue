<template>
	<div class="mt-8 bg-slate-800 rounded-xl shadow-md p-6">
		<div class="flex-x gap-2 mb-4">
			<span class="text-blue-600">⚙️</span>
			<h1 class="text-xl font-semibold">Define Schema and Fragment</h1>
		</div>

		<p class="mb-4 text-sm text-slate-500">
			To define the data point in the JSON content to split by:
		</p>

		<div class="flex items-stretch gap-8 text-slate-400">
			<div>
				<strong>1. Select a Schema</strong>: Defines the data structure.
				<pre class="bg-slate-900 text-sky-600 rounded p-3 text-xs mt-2 overflow-x-auto">[
  {
		"id": "prod-001",
		"name": "Wireless Mouse",
		"category": "Electronics",
		"brand": "LogiTech",
		"price": 39.99
  },
  {
		"id": "prod-001",
		"name": "Wireless Mouse",
		"category": "Electronics",
		"brand": "Sony",
		"price": 24.99
  }
]</pre>
			</div>
			<div>
				<strong>2. Select a Fragment</strong>: Choose the field to group by (e.g.,
				<code class="bg-sky-700 px-2 py-1 rounded">category</code>,
				<code class="bg-sky-700 px-2 py-1 rounded">brand</code>,
				<code class="bg-sky-700 px-2 py-1 rounded">price</code>).

				<div class="mt-4 bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
					<p>📌 <strong>Example 1:</strong> Splitting by <code class="bg-sky-700 px-2 py-1 rounded">brand</code> puts
						all "LogiTech" products in one artifact, "Sony" in another.</p>
				</div>
				<div class="mt-4 bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
					<p>📌 <strong>Example 2:</strong> Splitting by <code class="bg-sky-700 px-2 py-1 rounded">category</code> puts
						keeps all "Wireless Mouse" products in one artifact.</p>
				</div>
				<div class="mt-4 bg-sky-900 border-l-4 border-sky-500 p-3 text-sm">
					<p>📌 <strong>Example 3:</strong> Splitting by <code class="bg-sky-700 px-2 py-1 rounded">id</code> will put
						each product in a separate artifact.</p>
				</div>
			</div>
		</div>
	</div>
</template>
