import { ThePageLayout, ThePrimaryLayout } from "@/components/Layouts";
import { PromptDirectiveTable } from "@/components/Modules/Prompts";
import { authTeam, isAuthenticated, setAuthToken } from "@/helpers/auth";
import { AuthRoutes } from "@/routes/authRoutes";
import {
	AgentsView,
	AuditRequestsView,
	ContentSourcesView,
	DashboardView,
	LoginView,
	PageNotFoundView,
	SchemaDefinitionsView,
	TaskDefinitionsView,
	WhatsAppView,
	WorkflowDefinitionsView,
	WorkflowInputsView
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
					path: "/workflow-definitions/:id?/:panel?",
					name: "workflow-definitions",
					component: WorkflowDefinitionsView,
					meta: { title: "Workflow Definitions", type: "WorkflowDefinitionResource" }
				},
				{
					path: "/task-definitions/:id?/:panel?",
					name: "task-definitions",
					component: TaskDefinitionsView,
					meta: { title: "Task Definitions", type: "TaskDefinitionResource" }
				},
				{
					path: "/schemas/definitions/:id?/:panel?",
					name: "schema-definitions",
					component: SchemaDefinitionsView,
					meta: { title: "Schema Definitions", type: "SchemaDefinitionResource" }
				},
				{
					path: "directives/:id?/:panel?",
					name: "prompt-directives",
					component: PromptDirectiveTable,
					meta: { title: "Prompt Directives", type: "PromptDirectiveResource" }
				},
				{
					path: "/agents/:id?/:panel?/:thread_id?",
					name: "agents",
					component: AgentsView,
					meta: { title: "Agents", type: "AgentResource" }
				},
				{
					path: "/audit-requests/:id?/:panel?",
					name: "audit-requests",
					component: AuditRequestsView,
					meta: { title: "Auditing", type: "AuditRequestResource" }
				},
				{
					path: "/whatsapp-connections/:id?/:panel?",
					name: "whatsapp-connections",
					component: WhatsAppView,
					meta: { title: "WhatsApp", type: "WhatsAppConnectionResource" }
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
	document.title = (to.meta.title ? `${to.meta.title} | ` : "") + (authTeam.value?.name || "GPT Manager");
});

export default router;
