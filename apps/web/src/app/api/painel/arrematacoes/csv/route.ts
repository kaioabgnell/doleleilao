import { NextResponse, type NextRequest } from "next/server";
import { getToken } from "@/lib/session";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/** Download do CSV das arrematações (RF-G06): repassa o arquivo com o nome que a API deu. */
export async function GET(request: NextRequest) {
  const token = await getToken();
  if (!token) return NextResponse.redirect(new URL("/entrar", request.url));
  const auction = request.nextUrl.searchParams.get("lote");
  const query = auction && /^[0-9a-z]{26}$/.test(auction) ? `?auction=${auction}` : "";

  const response = await fetch(`${API_URL}/auctioneer/awards/export${query}`, {
    headers: { Accept: "text/csv", Authorization: `Bearer ${token}` },
    cache: "no-store",
  }).catch(() => null);
  if (!response?.ok) return NextResponse.json({ status: response?.status ?? 503, detail: "Não foi possível gerar o arquivo." }, { status: response?.status ?? 503 });

  return new NextResponse(response.body, {
    headers: {
      "Content-Type": "text/csv; charset=UTF-8",
      "Content-Disposition": response.headers.get("content-disposition") ?? 'attachment; filename="arrematacoes.csv"',
      "Cache-Control": "no-store",
    },
  });
}
