import { getActions } from "@/components/Modules/InputSources/inputSourceActions";
import { InputSourceController } from "@/components/Modules/InputSources/inputSourceControls";
import { fDate, fNumber } from "quasar-ui-danx";
import { TableColumn } from "quasar-ui-danx/types";
import { h } from "vue";

export const columns: TableColumn[] = [
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		required: true,
		actionMenu: getActions({ menu: true }),
		onClick: (inputSource) => InputSourceController.activatePanel(inputSource, "edit")
	},
	{
		name: "description",
		label: "Description",
		sortable: true,
		align: "left"
	},
	{
		name: "data",
		label: "Input",
		align: "left",
		vnode: () => h("div", "Render the data for a column")
	},
	{
		name: "workflow_runs_count",
		label: "InputSource Runs",
		align: "left",
		format: fNumber,
		sortable: true,
		onClick: (inputSource) => InputSourceController.activatePanel(inputSource, "runs")
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
