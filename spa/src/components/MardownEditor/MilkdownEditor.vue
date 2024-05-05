<script setup lang="ts">
import { defaultValueCtx, Editor, rootCtx } from "@milkdown/core";
import { block } from "@milkdown/plugin-block";
import { listener, listenerCtx } from "@milkdown/plugin-listener";
import { prism } from "@milkdown/plugin-prism";
import { commonmark } from "@milkdown/preset-commonmark";
import { nord } from "@milkdown/theme-nord";
import { Milkdown, useEditor } from "@milkdown/vue";
import "@milkdown/theme-nord/style.css";
import "prism-themes/themes/prism-nord.css";

const content = defineModel({ type: String });
useEditor((root) => {
	return Editor.make()
		.config(nord)
		.config((ctx) => {
			ctx.set(rootCtx, root);
			ctx.set(defaultValueCtx, content.value);
			ctx.get(listenerCtx).markdownUpdated((ctx, markdown) => {
				content.value = markdown;
			});
		})
		.use(prism)
		.use(block)
		.use(listener)
		.use(commonmark);
});
</script>

<template>
	<Milkdown />
</template>
