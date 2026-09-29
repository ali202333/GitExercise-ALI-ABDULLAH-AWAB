export default {
	async fetch(request, env) {
		const url = new URL(request.url);

		if (url.pathname.startsWith("/api/")) {
			const client = new DatabaseClient(env.scorebox_live);
			return handleAuthRequest(request, client, url);
		}

		if (url.pathname === "/" || url.pathname === "/proto2") {
			url.pathname = "/proto2.html";
		}

		return env.ASSETS.fetch(new Request(url, request));
	},
};

const SESSION_COOKIE = "scorebox_session";
const SESSION_LIFETIME = 60 * 60 * 24 * 30;
const PASSWORD_ITERATIONS = 100000;

class DatabaseClient {
	constructor(database) {
		this.database = database;
	}

	async query(sql, values = [], resultType = "run") {
		for (let attempt = 0; ; attempt++) {
			try {
				const statement = this.database.prepare(sql);
				const query = values.length ? statement.bind(...values) : statement;
				return resultType === "first" ? await query.first() : await query.run();
			} catch (error) {
				if (attempt > 0 || !isTransientD1Error(error)) throw error;
				await new Promise((resolve) => setTimeout(resolve, 100));
			}
		}
	}
}

function isTransientD1Error(error) {
	return /timeout|timed out|temporar|busy|locked|connection|network|fetch failed/i.test(String(error?.message || error));
}

async function handleAuthRequest(request, client, url) {
	const { pathname } = url;
	if (!["/api/signin", "/api/signup", "/api/session", "/api/logout"].includes(pathname)) {
		return new Response("Not found", { status: 404 });
	}

	if (pathname === "/api/session" && request.method === "GET") {
		await ensureAuthTables(client);
		const user = await getSessionUser(request, client);
		return Response.json({ user: user ? { username: user.username } : null });
	}

	if (pathname === "/api/logout" && (request.method === "POST" || request.method === "GET")) {
		await ensureAuthTables(client);
		const token = getCookie(request, SESSION_COOKIE);
		if (token) {
			await client.query("DELETE FROM scorebox_sessions WHERE token_hash = ?", [await sha256(token)]);
		}
		return new Response(null, {
			status: 303,
			headers: { "Location": new URL("/proto2.html", url).toString(), "Set-Cookie": sessionCookie("", 0) },
		});
	}

	if (request.method !== "POST" || !["/api/signin", "/api/signup"].includes(pathname)) {
		return new Response("Method not allowed", { status: 405 });
	}

	await ensureAuthTables(client);
	const form = await request.formData();
	const password = String(form.get("password") || "");
	if (pathname === "/api/signup") {
		const username = String(form.get("username") || form.get("name") || "").trim();
		const email = String(form.get("email") || "").trim().toLowerCase();
		const confirmPassword = String(form.get("confirm_password") || "");

		if (!username || username.length > 50 || !email || email.length > 255 || !/^\S+@\S+\.\S+$/.test(email)) {
			return authRedirect(url, "/signup.html?error=invalid");
		}
		if (password.length < 8 || password !== confirmPassword) {
			return authRedirect(url, `/signup.html?error=${password.length < 8 ? "password" : "mismatch"}`);
		}

		const existing = await client.query(
			"SELECT id FROM scorebox_users WHERE username = ? COLLATE NOCASE OR email = ? COLLATE NOCASE",
			[username, email],
			"first",
		);
		if (existing) return authRedirect(url, "/signup.html?error=exists");

		const passwordHash = await hashPassword(password);
		const insert = await client.query(
			"INSERT OR IGNORE INTO scorebox_users (username, email, password_hash) VALUES (?, ?, ?)",
			[username, email, passwordHash],
		);
		if (!insert.meta.changes) return authRedirect(url, "/signup.html?error=exists");
		return authRedirect(url, "/signin.html?registered=1");
	}

	const identifier = String(form.get("username") || form.get("email") || "").trim();
	if (!identifier || !password) return authRedirect(url, "/signin.html?error=credentials");
	const user = await client.query(
		"SELECT id, username, password_hash FROM scorebox_users WHERE username = ? COLLATE NOCASE OR email = ? COLLATE NOCASE",
		[identifier, identifier],
		"first",
	);
	if (!user || !(await verifyPassword(password, user.password_hash))) {
		return authRedirect(url, "/signin.html?error=credentials");
	}

	const token = randomToken();
	const expiresAt = Math.floor(Date.now() / 1000) + SESSION_LIFETIME;
	await client.query(
		"INSERT OR IGNORE INTO scorebox_sessions (token_hash, user_id, expires_at) VALUES (?, ?, ?)",
		[await sha256(token), user.id, expiresAt],
	);
	return new Response(null, {
		status: 303,
		headers: {
			"Location": new URL("/proto2.html?logged_in=1", url).toString(),
			"Set-Cookie": sessionCookie(token, SESSION_LIFETIME),
		},
	});
}

async function ensureAuthTables(client) {
	await client.query(`
		CREATE TABLE IF NOT EXISTS scorebox_users (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			username TEXT NOT NULL COLLATE NOCASE UNIQUE,
			email TEXT NOT NULL COLLATE NOCASE UNIQUE,
			password_hash TEXT NOT NULL,
			created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
		)
	`);
	await client.query(`
		CREATE TABLE IF NOT EXISTS scorebox_sessions (
			token_hash TEXT PRIMARY KEY,
			user_id INTEGER NOT NULL REFERENCES scorebox_users(id) ON DELETE CASCADE,
			expires_at INTEGER NOT NULL
		)
	`);
}

async function getSessionUser(request, client) {
	const token = getCookie(request, SESSION_COOKIE);
	if (!token) return null;
	const result = await client.query(`
		SELECT scorebox_users.id, scorebox_users.username
		FROM scorebox_sessions JOIN scorebox_users ON scorebox_users.id = scorebox_sessions.user_id
		WHERE scorebox_sessions.token_hash = ? AND scorebox_sessions.expires_at > ?
	`, [await sha256(token), Math.floor(Date.now() / 1000)], "first");
	return result || null;
}

async function hashPassword(password) {
	const salt = crypto.getRandomValues(new Uint8Array(16));
	const key = await crypto.subtle.importKey("raw", new TextEncoder().encode(password), "PBKDF2", false, ["deriveBits"]);
	const hash = new Uint8Array(await crypto.subtle.deriveBits(
		{ name: "PBKDF2", hash: "SHA-256", salt, iterations: PASSWORD_ITERATIONS }, key, 256,
	));
	return `pbkdf2$${PASSWORD_ITERATIONS}$${toBase64(salt)}$${toBase64(hash)}`;
}

async function verifyPassword(password, storedHash) {
	const [algorithm, iterationsText, saltText, expectedText] = String(storedHash).split("$");
	const iterations = Number(iterationsText);
	if (algorithm !== "pbkdf2" || !Number.isInteger(iterations) || iterations < 1 || !saltText || !expectedText) return false;
	const key = await crypto.subtle.importKey("raw", new TextEncoder().encode(password), "PBKDF2", false, ["deriveBits"]);
	const actual = new Uint8Array(await crypto.subtle.deriveBits(
		{ name: "PBKDF2", hash: "SHA-256", salt: fromBase64(saltText), iterations }, key, 256,
	));
	const expected = fromBase64(expectedText);
	return actual.length === expected.length && actual.every((byte, index) => byte === expected[index]);
}

function randomToken() {
	return Array.from(crypto.getRandomValues(new Uint8Array(32)), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function sha256(value) {
	const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
	return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

function toBase64(bytes) {
	return btoa(String.fromCharCode(...bytes));
}

function fromBase64(value) {
	return Uint8Array.from(atob(value), (character) => character.charCodeAt(0));
}

function getCookie(request, name) {
	return request.headers.get("Cookie")?.split(";").map((part) => part.trim())
		.find((part) => part.startsWith(`${name}=`))?.slice(name.length + 1) || "";
}

function sessionCookie(token, maxAge) {
	return `${SESSION_COOKIE}=${token}; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=${maxAge}`;
}

function authRedirect(requestUrl, path) {
	return new Response(null, { status: 303, headers: { "Location": new URL(path, requestUrl).toString() } });
}