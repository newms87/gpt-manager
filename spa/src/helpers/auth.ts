/**
 * Check if the user is authenticated via the token stored in laravel_session
 */
export function isAuthenticated() {
	console.log("is auth", getCookie("laravel_session"));
	return !!getCookie("laravel_session");

}

export function getCookie(key: string) {
	const cookies = document.cookie.split("; ");
	const cookie = cookies.find(cookie => cookie.startsWith(key));
	return cookie?.split("=")[1];
}
