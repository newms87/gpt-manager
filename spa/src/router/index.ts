import { ThePageLayout, ThePrimaryLayout } from "@/components/Layouts";
import { siteSettings } from "@/config";
import { isAuthenticated, setAuthToken } from "@/helpers/auth";
import { AuthRoutes } from "@/routes/authRoutes";
import {
	AgentsView,
	AuditRequestsView,
	ContentSourcesView,
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
					path: "/content-sources/:id?/:panel?",
					name: "content-sources",
					component: ContentSourcesView,
					meta: { title: "Content Sources", type: "ContentSourceResource" }
				},
				{
					path: "/workflow-inputs/:id?/:panel?",
					name: "workflow-inputs",
					component: WorkflowInputsView,
					meta: { title: "Workflow Inputs", type: "WorkflowInputResource" }
				},
				{
					path: "/workflows/:id?/:panel?",
					name: "workflows",
					component: WorkflowsView,
					meta: { title: "Workflows", type: "WorkflowResource" }
				},
				{
					path: "/agents/:id?/:panel?",
					name: "agents",
					component: AgentsView,
					meta: { title: "Agents", type: "AgentResource" }
				},
				{
					path: "/audit-requests/:id?/:panel?",
					name: "audit-requests",
					component: AuditRequestsView,
					meta: { title: "Auditing", type: "AuditRequestResource" }
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
