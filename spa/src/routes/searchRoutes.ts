import { SearchItem } from "@/types/research";
import { request } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const AiSearchRoutes = {
	search: (query: string) => request.get(API_URL + `/tortguard/search?query=${query}`),
	research: (search_result: SearchItem) => request.get(API_URL + `/tortguard/research?search_result=${JSON.stringify(search_result)}`)
};
