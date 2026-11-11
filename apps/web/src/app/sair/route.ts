import { NextResponse, type NextRequest } from "next/server";
import { api } from "@/lib/api";
import { clearSession, getToken } from "@/lib/session";

/**
 * Encerra a sessão do painel: revoga o token na API (se ainda valer), apaga o cookie e volta ao login.
 * Também recebe quem teve a sessão vencida ou a conta suspensa (?motivo=expirou|suspensa).
 */
export async function GET(request: NextRequest) {
  const token = await getToken();
  if (token) await api("/auctioneer/sessions/current", { method: "DELETE", token });
  await clearSession();

  const motivo = request.nextUrl.searchParams.get("motivo");
  const url = new URL("/entrar", request.url);
  url.searchParams.set("motivo", motivo === "expirou" || motivo === "suspensa" ? motivo : "saiu");
  return NextResponse.redirect(url, { status: 303 });
}
