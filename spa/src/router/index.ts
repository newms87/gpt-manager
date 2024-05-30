import ThePageLayout from "@/components/Layouts/ThePageLayout";
import ThePrimaryLayout from "@/components/Layouts/ThePrimaryLayout";
import { siteSettings } from "@/config";
import { isAuthenticated, setAuthToken } from "@/helpers/auth";
import { AuthRoutes } from "@/routes/authRoutes";
import {
	AgentsView,
	AuditRequestsView,
	DashboardView,
	LoginView,
	PageNotFoundView,
	WorkflowInputsView,
	WorkflowsView
} from "@/views";
import { FlashMessages } from "quasar-ui-danx";
import { createRouter, createWebHistory } from "vue-router";

const router = createRouter({
	history: createWebHistory(import.meta.env.BASE_URL),
	routes: [
		{
			path: "/",
			name: "home",
			redirect: { name: "dashboard" },
			component: ThePageLayout,
			children: [
				{
					path: "/dashboard",
					alias: "/api/dashboard",
					name: "dashboard",
					component: DashboardView,
					meta: { title: "Danx Home" }
				},
				{
					path: "/workflow-inputs/:id?/:panel?",
					name: "workflow-inputs",
					component: WorkflowInputsView,
					meta: { title: "Workflow Inputs", type: "WorkflowInput" }
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
				}
			]
		},
		{
			path: "/auth",
			name: "auth",
			redirect: { name: "auth.login" },
			component: ThePrimaryLayout,
			children: [
				{
					path: "/login",
					name: "auth.login",
					component: LoginView,
					meta: { title: "Login" }
				},
				{
					path: "/logout",
					name: "auth.logout",
					beforeEnter: async () => {
						const result = await AuthRoutes.logout();
						if (result.error) {
							FlashMessages.error(result.message || "An error occurred while logging you out. Please contact us for help");
						}
						setAuthToken("");
						return { name: "auth.login" };
					},
					meta: { title: "Logout" },
					component: PageNotFoundView
				}
			]
		},
		{
			path: "/:pathMatch(.*)*",
			component: PageNotFoundView
		}
	]
});

// Login navigation guard
router.beforeEach(async (to) => {
	const isLogin = to.name === "auth.login";

	if (!isLogin && !isAuthenticated()) {
		return { name: "auth.login" };
	}

	if (isLogin && isAuthenticated()) {
		return { name: "home" };
	}
});

router.afterEach(to => {
	document.title = (to.meta.title ? `${to.meta.title} | ` : "") + siteSettings.value.name;
});

export default router;
