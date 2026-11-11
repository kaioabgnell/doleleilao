import { NextResponse, type NextRequest } from "next/server";
import { getBidderToken } from "@/lib/bidder-session";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/**
 * Conta a visualização do produto (RF-C04). Visitante anônimo ganha uma chave aleatória no cookie
 * `dl_v` (sem dado pessoal); a API desduplica por 30 min.
 */
export async function POST(request: NextRequest, { params }: RouteContext<"/[slug]/api/visualizacao/[produto]">) {
  const { slug, produto } = await params;
  const viewer = request.cookies.get("dl_v")?.value ?? crypto.randomUUID();
  const token = await getBidderToken();

  const response = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/products/${encodeURIComponent(produto)}/views`, {
    method: "POST",
    headers: { Accept: "application/json", "X-Viewer-Key": viewer, ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    cache: "no-store",
  }).catch(() => null);

  const res = NextResponse.json(response?.ok ? await response.json() : { views: null }, { status: response?.ok ? 200 : 202 });
  if (!request.cookies.has("dl_v")) {
    res.cookies.set("dl_v", viewer, { httpOnly: true, sameSite: "lax", path: "/", maxAge: 60 * 60 * 24 * 365, secure: process.env.NODE_ENV === "production" });
  }
  return res;
}
