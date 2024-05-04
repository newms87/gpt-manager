<script setup lang="ts">
import { defineComponent, provide, ref } from "vue";

// Create a symbol for the key
const editorInfoCtxKey = Symbol("editorInfoCtxKey");

// Define the MilkdownProvider component
defineComponent({
	name: "MilkdownProvider",
	setup() {
		// Create refs for the data you want to provide
		const editorFactory = ref(null);

		// Provide the data using the editorInfoCtxKey
		provide(editorInfoCtxKey, {
			editorFactory
		});

		return {
			editorFactory
		};
	}
});
</script>
<template>
	<slot />
</template>
