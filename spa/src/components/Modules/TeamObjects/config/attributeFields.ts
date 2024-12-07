import { MarkdownEditor } from "@/components/MarkdownEditor";
import { SelectField, TextField } from "quasar-ui-danx";
import { h } from "vue";

export const attributeFields = [
	{
		name: "name"
	},
	{
		name: "date"
	},
	{
		name: "value",
		label: "Value",
		vnode: (props) => h(MarkdownEditor, { ...props, maxLength: 64000 })
	},
	{
		name: "confidence",
		label: "Confidence",
		vnode: (props) => h(SelectField, {
			...props, options: [
				{ label: "High", value: "High" },
				{ label: "Medium", value: "Medium" },
				{ label: "Low", value: "Low" }
			]
		})
	},
	{
		name: "description",
		label: "Explanation of confidence and source",
		vnode: (props) => h(TextField, { ...props, type: "textarea", inputClass: "h-56", maxLength: 64000 })
	}
];
