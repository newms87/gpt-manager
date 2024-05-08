<script setup lang="ts">
import { defaultValueCtx, Editor, editorViewOptionsCtx, rootCtx } from "@milkdown/core";
import { block } from "@milkdown/plugin-block";
import { history } from "@milkdown/plugin-history";
import { listener, listenerCtx } from "@milkdown/plugin-listener";
import { prism } from "@milkdown/plugin-prism";
import { commonmark } from "@milkdown/preset-commonmark";
import { nord } from "@milkdown/theme-nord";
import { Milkdown, useEditor } from "@milkdown/vue";
// eslint-disable-next-line import/extensions
import "@milkdown/theme-nord/style.css";
import "prism-themes/themes/prism-nord.css";
import "prosemirror-view/style/prosemirror.css";
import "prosemirror-tables/style/tables.css";

const content = defineModel({ type: String });
const props = defineProps<{
	readonly?: boolean
}>();
useEditor((root) => {
	return Editor.make()
		.config(nord)
		.config((ctx) => {
			ctx.set(rootCtx, root);
			ctx.set(defaultValueCtx, content.value || "");
			ctx.get(listenerCtx).markdownUpdated((ctx, markdown) => {
				content.value = markdown;
			});
			ctx.set(editorViewOptionsCtx, { editable: () => !props.readonly });
		})
		.use(history)
		.use(prism)
		.use(block)
		.use(listener)
		.use(commonmark);
});
</script>

<template>
	<Milkdown />
</template>
