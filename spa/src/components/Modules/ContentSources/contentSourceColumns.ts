import { getActions } from "@/components/Modules/ContentSources/contentSourceActions";
import { dxContentSource } from "@/components/Modules/ContentSources/contentSourceControls";
import router from "@/router";
import { fDate, fNumber, TableColumn } from "quasar-ui-danx";

export const columns: TableColumn[] = [
	{
		name: "menu",
		label: "",
		required: true,
		hideContent: true,
		shrink: true,
		actionMenu: getActions({ menu: true })
	},
	{
		name: "id",
		label: "ID",
		sortable: true,
		align: "left",
		shrink: true,
		onClick: (agent) => dxContentSource.activatePanel(agent, "edit")
	},
	{
		name: "name",
		label: "Name",
		align: "left",
		sortable: true,
		onClick: (workflowInput) => dxContentSource.activatePanel(workflowInput, "edit")
	},
	{
		name: "type",
		label: "Source Type",
		sortable: true,
		align: "left"
	},
	{
		name: "url",
		label: "URL",
		sortable: true,
		align: "left"
	},
	{
		name: "per_page",
		label: "Per Page",
		sortable: true,
		align: "left",
		format: fNumber
	},
	{
		name: "last_checkpoint",
		label: "Checkpoint",
		align: "left",
		sortable: true
	},
	{
		name: "fetched_at",
		label: "Last Fetched",
		sortable: true,
		align: "left",
		format: fDate
	},
	{
		name: "workflow_inputs_count",
		label: "Workflow Inputs",
		align: "left",
		format: fNumber,
		sortable: true,
		onClick: (workflowInput) => router.push({
			name: "workflow-inputs",
			query: { filter: JSON.stringify({ content_source_id: workflowInput.id }) }
		})
	},
	{
		name: "created_at",
		label: "Created Date",
		sortable: true,
		align: "left",
		format: fDate
	}
];
