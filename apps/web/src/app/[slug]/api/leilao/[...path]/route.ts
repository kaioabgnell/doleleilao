import { NextResponse, type NextRequest } from "next/server";
import { getBidderToken } from "@/lib/bidder-session";
import { isSameOrigin } from "@/lib/same-origin";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";
const ID = "[0-9a-z]{26}";
const AUCTION = "[a-z0-9-]{1,100}";

/**
 * BFF do auditório (o cookie do participante só existe dentro de /{slug}). Cada caminho daqui vira
 * um caminho da API; o resto responde 404.
 */
/** auth: "required" exige o cookie; "optional" manda o token se houver (o chat muda conforme quem vê). */
const ROUTES: { method: string; pattern: RegExp; to: (m: RegExpMatchArray, slug: string) => string; auth: boolean | "optional" }[] = [
  { method: "GET", pattern: new RegExp(`^estado/(${AUCTION})$`), to: (m, slug) => `/storefronts/${slug}/auctions/${m[1]}/state`, auth: false },
  { method: "GET", pattern: new RegExp(`^situacao/(${AUCTION})$`), to: (m) => `/bidders/me/auctions/${m[1]}`, auth: true },
  { method: "PUT", pattern: new RegExp(`^participar/(${AUCTION})$`), to: (m) => `/bidders/me/participations/${m[1]}`, auth: true },
  { method: "DELETE", pattern: new RegExp(`^participar/(${AUCTION})$`), to: (m) => `/bidders/me/participations/${m[1]}`, auth: true },
  { method: "POST", pattern: new RegExp(`^lances/(${ID})$`), to: (m) => `/items/${m[1]}/bids`, auth: true },
  { method: "PUT", pattern: new RegExp(`^automatico/(${ID})$`), to: (m) => `/items/${m[1]}/auto-bid`, auth: true },
  { method: "DELETE", pattern: new RegExp(`^automatico/(${ID})$`), to: (m) => `/items/${m[1]}/auto-bid`, auth: true },
  { method: "POST", pattern: /^canal$/, to: () => "/broadcasting/auth", auth: true },
  { method: "GET", pattern: new RegExp(`^chat/(${AUCTION})$`), to: (m, slug) => `/storefronts/${slug}/auctions/${m[1]}/chat`, auth: "optional" },
  { method: "POST", pattern: new RegExp(`^chat/(${AUCTION})$`), to: (m) => `/bidders/me/auctions/${m[1]}/chat`, auth: true },
];

async function forward(request: NextRequest, { params }: RouteContext<"/[slug]/api/leilao/[...path]">) {
  if (request.method !== "GET" && !isSameOrigin(request)) return NextResponse.json({ status: 403 }, { status: 403 });
  const { slug, path } = await params;
  const joined = path.join("/");

  let target: string | null = null;
  let needsAuth: boolean | "optional" = false;
  for (const route of ROUTES) {
    const match = route.method === request.method ? joined.match(route.pattern) : null;
    if (match) {
      target = route.to(match, encodeURIComponent(slug));
      needsAuth = route.auth;
      break;
    }
  }
  if (!target) return NextResponse.json({ status: 404 }, { status: 404 });

  const headers = new Headers({ Accept: "application/json" });
  if (needsAuth) {
    const token = await getBidderToken();
    if (!token && needsAuth === true) return NextResponse.json({ status: 401, detail: "Entre na sua conta para continuar." }, { status: 401 });
    if (token) headers.set("Authorization", `Bearer ${token}`);
  }
  const contentType = request.headers.get("content-type");
  if (contentType) headers.set("Content-Type", contentType);
  const idempotencyKey = request.headers.get("idempotency-key");
  if (idempotencyKey) headers.set("Idempotency-Key", idempotencyKey);
  const ip = request.headers.get("cf-connecting-ip") ?? request.headers.get("x-forwarded-for")?.split(",")[0]?.trim();
  if (ip) headers.set("X-Forwarded-For", ip);

  const response = await fetch(`${API_URL}${target}${request.method === "GET" ? request.nextUrl.search : ""}`, {
    method: request.method,
    headers,
    body: ["GET", "HEAD"].includes(request.method) ? undefined : await request.arrayBuffer(),
    cache: "no-store",
  }).catch(() => null);

  if (!response) return NextResponse.json({ status: 503, detail: "Sem conexão com o servidor. Tente de novo." }, { status: 503 });
  if (response.status === 204) return new NextResponse(null, { status: 204 });
  return new NextResponse(await response.text(), {
    status: response.status,
    headers: { "Content-Type": response.headers.get("content-type") ?? "application/json", "Cache-Control": "no-store" },
  });
}

export const GET = forward;
export const POST = forward;
export const PUT = forward;
export const DELETE = forward;
