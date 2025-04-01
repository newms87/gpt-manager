export interface AuthUser {
	id: number;
	name: string;
	email: string;
}

export interface AuthTeam {
	id: number;
	uuid: string;
	name: string;
	namespace: string;
	logo: string;
}
