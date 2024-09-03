/// <reference types="vite/client" />

declare module "*.svg" {
	const content: never;
	// @ts-expect-error - Not sure what typescript deal is here w/ duplicate declared var...
	export default content;
}

declare module "*.vue" {
	import type { DefineComponent } from "vue";
	const component: DefineComponent<object, object, any>;
	export default component;
}

