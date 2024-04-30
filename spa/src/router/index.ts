import { AgentsView, DashboardView } from "@/views";
import { createRouter, createWebHistory } from "vue-router";

const router = createRouter({
	history: createWebHistory(import.meta.env.BASE_URL),
	routes: [
		{
			path: "/",
			name: "home",
			component: DashboardView,
			meta: { title: "Danx Home" }
		},
		{
			path: "/agents",
			name: "agents",
			component: AgentsView,
			meta: { title: "Agents" }
		}
	]
});

export default router;
