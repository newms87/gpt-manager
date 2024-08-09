import { request } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const TortguardRoutes = {
	drugInjury: (id: number) => request.get(`${API_URL}/tortguard/drug-injury/${id}`)
};
