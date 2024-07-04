/// <reference types="vite/client" />

declare module "*.svg" {
	const content: never;
	export default content;
}

declare module "*.vue" {
	import type { DefineComponent } from "vue";
	const component: DefineComponent<object, object, any>;
	export default component;
}

