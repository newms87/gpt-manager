import { SearchResultItemBySideEffect } from "@/types/research";
import { request } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const AiSearchRoutes = {
	search: (query: string) => request.get(API_URL + `/tortguard/search?query=${query}`),
	research: (input: SearchResultItemBySideEffect) => request.post(API_URL + `/tortguard/research`, { input })
};
