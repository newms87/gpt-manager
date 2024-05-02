interface Agent {
	name: string;
	model: string;
	temperature: string;
	description: string;
	prompt: string;
	threads: AgentThread[];
}

interface AgentThread {
	id: number;
	name: string;
	summary: string;
	messages: ThreadMessage[];
}

interface ThreadMessage {
	id: number;
	role: "assistant" | "user";
	title: string;
	content: string;
}
