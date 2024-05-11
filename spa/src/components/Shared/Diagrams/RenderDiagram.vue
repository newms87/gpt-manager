<template>
	<div ref="diagramDiv" class="w-full h-full"></div>
</template>
<script setup lang="ts">
import mermaid from "mermaid";
import { onMounted, ref, watch } from "vue";

const props = withDefaults(defineProps<{
	diagram: string;
	type?: string;
	theme?: string;
}>(), {
	type: "flowchart TD",
	theme: "default"
});


const diagramDiv = ref(null);
onMounted(() => {
	mermaid.initialize({
		startOnLoad: false,
		theme: props.theme
	});
	drawDiagram();
});

async function drawDiagram() {
	const { svg } = await mermaid.render("graphDiv", `${props.type}\n${props.diagram}`);
	diagramDiv.value.innerHTML = svg;
}
watch(() => props.diagram, drawDiagram);
</script>
