import { InputSource } from "@/components/Modules/InputSources/input-sources";
import { InputSourceController } from "@/components/Modules/InputSources/inputSourceControls";
import { InputSourceInfoPanel, InputSourceWorkflowRunsPanel } from "@/components/Modules/InputSources/Panels";
import { BadgeTab } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx/types";
import { computed, h } from "vue";

const activeItem = computed<InputSource>(() => InputSourceController.activeItem.value);

export const panels = computed<ActionPanel[]>(() => [
	{
		name: "edit",
		label: "Details",
		vnode: () => h(InputSourceInfoPanel, {
			inputSource: activeItem.value
		})
	},
	{
		name: "runs",
		label: "Workflow Runs",
		tabVnode: () => h(BadgeTab, { count: activeItem.value.workflow_runs_count }),
		vnode: () => h(InputSourceWorkflowRunsPanel, {
			inputSource: activeItem.value?.workflowRuns
		})
	}
]);
