import { NextResponse, type NextRequest } from "next/server";
import { api } from "@/lib/api";
import type { NotificationPage } from "@/lib/api-types";
import { getToken } from "@/lib/session";

/** Sino do painel: lista e contador (RF-N01, RF-N02). */
export async function GET(request: NextRequest) {
  const token = await getToken();
  if (!token) return NextResponse.json({ status: 401 }, { status: 401 });

  const cursor = request.nextUrl.searchParams.get("cursor");
  const result = await api<NotificationPage>(
    `/auctioneer/notifications${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ""}`,
    { token },
  );
  return NextResponse.json(result.ok ? result.data : result.problem, {
    status: result.status,
    headers: { "Cache-Control": "no-store" },
  });
}
