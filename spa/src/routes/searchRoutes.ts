import { request } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const AiSearchRoutes = {
	search: (query: string) => request.get(API_URL + `/tortguard/search?query=${query}`),
	research: (product: string, injury: string) => request.get(API_URL + `/tortguard/research?product=${product}&injury=${injury}`)
};
