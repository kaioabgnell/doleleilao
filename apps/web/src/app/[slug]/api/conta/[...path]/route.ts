import { NextResponse, type NextRequest } from "next/server";
import { getBidderToken } from "@/lib/bidder-session";
import { isSameOrigin } from "@/lib/same-origin";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/** Só estes caminhos de /bidders/me passam por aqui (chamadas do navegador na loja). */
const ALLOWED = [/^avatar$/, /^photo$/, /^favorites\/[0-9a-z]{26}$/, /^notifications$/, /^notifications\/read$/, /^email\/verification$/];

/** BFF da conta do participante: o cookie de sessão só existe dentro de /{slug}, por isso a rota mora aqui. */
async function forward(request: NextRequest, { params }: RouteContext<"/[slug]/api/conta/[...path]">) {
  if (request.method !== "GET" && !isSameOrigin(request)) return NextResponse.json({ status: 403 }, { status: 403 });
  const path = (await params).path.join("/");
  if (!ALLOWED.some((p) => p.test(path))) return NextResponse.json({ status: 404 }, { status: 404 });

  const token = await getBidderToken();
  if (!token) return NextResponse.json({ status: 401, detail: "Entre na sua conta para continuar." }, { status: 401 });

  const headers = new Headers({ Accept: "application/json", Authorization: `Bearer ${token}` });
  const contentType = request.headers.get("content-type");
  if (contentType) headers.set("Content-Type", contentType);
  const response = await fetch(`${API_URL}/bidders/me/${path}${request.nextUrl.search}`, {
    method: request.method,
    headers,
    body: ["GET", "HEAD"].includes(request.method) ? undefined : await request.arrayBuffer(),
    cache: "no-store",
  }).catch(() => null);

  if (!response) return NextResponse.json({ status: 503, detail: "Sem conexão com o servidor." }, { status: 503 });
  if (response.status === 204) return new NextResponse(null, { status: 204 });
  return new NextResponse(await response.text(), { status: response.status, headers: { "Content-Type": "application/json" } });
}

export { forward as GET, forward as POST, forward as PUT, forward as DELETE };
