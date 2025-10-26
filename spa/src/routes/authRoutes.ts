import { apiUrls } from "@/api";
import { request } from "quasar-ui-danx";

export const AuthRoutes = {
	login: (input) => request.post(apiUrls.auth.login, input),
	loginToTeam: (input) => request.post(apiUrls.auth.loginToTeam, input),
	logout: () => request.post(apiUrls.auth.logout)
};
