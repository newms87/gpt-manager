export interface AuthUser {
	id: number;
	name: string;
	email: string;
	can?: {
		viewDeveloperTools?: boolean;
		viewAuditing?: boolean;
		viewJobsInUi?: boolean;
		[key: string]: boolean | undefined;
	};
}

export interface AuthTeam {
	id: number;
	uuid: string;
	name: string;
	namespace: string;
	logo: string;
}
