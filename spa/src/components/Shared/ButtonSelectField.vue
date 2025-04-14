<template>
	<QTabs
		v-model="filterCondition.field"
		class="tab-buttons border-sky-900 !w-[20rem] bg-sky-950"
		indicator-color="sky-900"
		@update:model-value="handleFieldChange"
	>
		<QTab name="text_content">
			<TextIcon class="w-4" />
			<QTooltip>Filter on text</QTooltip>
		</QTab>
		<QTab name="json_content">
			<JsonIcon class="w-4" />
			<QTooltip>Filter on JSON Content</QTooltip>
		</QTab>
		<QTab name="meta">
			<MetaIcon class="w-4" />
			<QTooltip>Filter on metadata</QTooltip>
		</QTab>
		<QTab name="storedFiles">
			<FilesIcon class="w-4" />
			<QTooltip>Filter on files</QTooltip>
		</QTab>
	</QTabs>
</template>
