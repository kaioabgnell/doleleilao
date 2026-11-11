import { NextResponse, type NextRequest } from "next/server";
import { getBidderToken } from "@/lib/bidder-session";
import { isSameOrigin } from "@/lib/same-origin";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/** "Quer leiloar algo seu?" (RF-E09): repassa o formulário (com fotos) para a API. */
export async function POST(request: NextRequest, { params }: RouteContext<"/[slug]/api/quero-leiloar">) {
  if (!isSameOrigin(request)) return NextResponse.json({ status: 403 }, { status: 403 });
  const { slug } = await params;
  const token = await getBidderToken();
  const ip = request.headers.get("cf-connecting-ip") ?? request.headers.get("x-forwarded-for")?.split(",")[0]?.trim();

  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/consignment-requests`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": request.headers.get("content-type") ?? "multipart/form-data",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(ip ? { "X-Forwarded-For": ip } : {}),
    },
    body: await request.arrayBuffer(),
    cache: "no-store",
  }).catch(() => null);

  if (!response) return NextResponse.json({ status: 503, detail: "Sem conexão com o servidor." }, { status: 503 });
  return new NextResponse(await response.text(), { status: response.status, headers: { "Content-Type": "application/json" } });
}
