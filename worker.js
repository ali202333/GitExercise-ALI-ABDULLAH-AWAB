export default {
	async fetch(request, env) {
		const url = new URL(request.url);

		if (url.pathname === "/" || url.pathname === "/proto2") {
			url.pathname = "/proto2.html";
		}

		return env.ASSETS.fetch(new Request(url, request));
	},
};