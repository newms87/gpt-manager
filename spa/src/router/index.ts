import { isAuthenticated, setAuthToken } from "@/helpers/auth";
import {
	AgentsView,
	AuditRequestsView,
	DashboardView,
	InputSourcesView,
	LoginView,
	PageNotFoundView,
	WorkflowsView
} from "@/views";
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
			path: "/login",
			name: "login",
			component: LoginView,
			meta: { title: "Login" }
		},
		{
			path: "/logout",
			name: "logout",
			beforeEnter: () => {
				setAuthToken("");
				return { name: "login" };
			},
			meta: { title: "Logout" },
			component: PageNotFoundView
		},
		{
			path: "/input-sources/:id?/:panel?",
			name: "input-sources",
			component: InputSourcesView,
			meta: { title: "Input Sources", type: "InputSource" }
		},
		{
			path: "/workflows/:id?/:panel?",
			name: "workflows",
			component: WorkflowsView,
			meta: { title: "Workflows", type: "Workflow" }
		},
		{
			path: "/agents/:id?/:panel?",
			name: "agents",
			component: AgentsView,
			meta: { title: "Agents", type: "Agent" }
		},
		{
			path: "/audit-requests/:id?/:panel?",
			name: "audit-requests",
			component: AuditRequestsView,
			meta: { title: "Auditing", type: "AuditRequest" }
		},
		{
			path: "/:pathMatch(.*)*",
			component: PageNotFoundView
		}
	]
});

// Login navigation guard
router.beforeEach(async (to, from) => {
	const isLogin = to.name === "login";

	if (!isLogin && !isAuthenticated()) {
		return { name: "login" };
	}

	if (isLogin && isAuthenticated()) {
		return { name: "home" };
	}
});

router.afterEach(to => {
	document.title = (to.meta.title ? `${to.meta.title} | ` : "") + "Sage Sweeper";
});

export default router;
