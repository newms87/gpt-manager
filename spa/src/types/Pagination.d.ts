export interface PaginationModel {
	page: number;
	perPage: number;
	total: number;
}

export type PaginationEmits = {
	"update:page": [number]
	"update:perPage": [number]
}
