import { NextResponse, type NextRequest } from "next/server";
import { isSameOrigin } from "@/lib/same-origin";
import { getToken } from "@/lib/session";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/** Só estes caminhos do painel passam por aqui (uploads em multipart e listas). */
const ALLOWED = [
  /^storefront\/logo$/,
  /^storefront\/terms$/,
  /^banners$/,
  /^banners\/order$/,
  /^banners\/[0-9a-z]{26}$/,
  /^categories$/,
  /^categories\/order$/,
  /^categories\/[0-9a-z]{26}$/,
  /^products$/,
  /^products\/attribute-names$/,
  /^products\/[0-9a-z]{26}$/,
  /^products\/[0-9a-z]{26}\/photos$/,
  /^products\/[0-9a-z]{26}\/history$/,
  /^products\/[0-9a-z]{26}\/return-to-catalog$/,
  /^products\/[0-9a-z]{26}\/photos\/order$/,
  /^products\/[0-9a-z]{26}\/photos\/[0-9a-z]{26}$/,
  /^consignment-requests\/[0-9a-z]{26}$/,
  /^auctions$/,
  /^auctions\/selectable-products$/,
  /^auctions\/[0-9a-z]{26}$/,
  /^auctions\/[0-9a-z]{26}\/(schedule|unschedule|cancel|visibility|cover|live|start|pause|resume)$/,
  /^items\/[0-9a-z]{26}\/(close|skip|void-last-bid)$/,
  /^auctions\/[0-9a-z]{26}\/chat$/,
  /^auctions\/[0-9a-z]{26}\/chat\/[0-9a-z]{26}$/,
  /^auctions\/[0-9a-z]{26}\/mutes\/[0-9a-z]{26}$/,
  /^bidders\/[0-9a-z]{26}\/ban$/,
  /^broadcasting\/auth$/,
  /^awards$/,
  /^awards\/[0-9a-z]{26}$/,
  /^awards\/[0-9a-z]{26}\/message$/,
  /^winner-message-template$/,
  /^billing$/,
  /^billing\/card$/,
];

/**
 * BFF do conteúdo da loja (logo, banners, categorias): repassa a requisição para /api/v1/auctioneer/…
 * com o token do cookie. O corpo vai como veio (multipart com imagens ou JSON).
 */
async function forward(request: NextRequest, { params }: RouteContext<"/api/painel/conteudo/[...path]">) {
  if (request.method !== "GET" && !isSameOrigin(request)) return NextResponse.json({ status: 403 }, { status: 403 });

  const path = (await params).path.join("/");
  const query = request.nextUrl.search;
  if (!ALLOWED.some((pattern) => pattern.test(path))) return NextResponse.json({ status: 404 }, { status: 404 });

  const token = await getToken();
  if (!token) return NextResponse.json({ status: 401, detail: "Entre na sua conta para continuar." }, { status: 401 });

  const headers = new Headers({ Accept: "application/json", Authorization: `Bearer ${token}` });
  const contentType = request.headers.get("content-type");
  if (contentType) headers.set("Content-Type", contentType);
  const ip = request.headers.get("cf-connecting-ip") ?? request.headers.get("x-forwarded-for")?.split(",")[0]?.trim();
  if (ip) headers.set("X-Forwarded-For", ip);

  const hasBody = !["GET", "HEAD"].includes(request.method);
  try {
    const response = await fetch(`${API_URL}/auctioneer/${path}${query}`, {
      method: request.method,
      headers,
      body: hasBody ? await request.arrayBuffer() : undefined,
      cache: "no-store",
    });
    if (response.status === 204) return new NextResponse(null, { status: 204 });
    return new NextResponse(await response.text(), {
      status: response.status,
      headers: { "Content-Type": response.headers.get("content-type") ?? "application/json" },
    });
  } catch {
    return NextResponse.json(
      { status: 503, detail: "Não conseguimos falar com o servidor. Tente de novo em instantes." },
      { status: 503 },
    );
  }
}

export { forward as GET, forward as POST, forward as PUT, forward as PATCH, forward as DELETE };
