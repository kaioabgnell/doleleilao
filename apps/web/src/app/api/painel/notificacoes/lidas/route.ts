import { NextResponse, type NextRequest } from "next/server";
import { api } from "@/lib/api";
import { isSameOrigin } from "@/lib/same-origin";
import { getToken } from "@/lib/session";

/** Marca notificações como lidas: `{ ids }` ou, sem ids, todas. */
export async function POST(request: NextRequest) {
  if (!isSameOrigin(request)) return NextResponse.json({ status: 403 }, { status: 403 });
  const token = await getToken();
  if (!token) return NextResponse.json({ status: 401 }, { status: 401 });

  const body = (await request.json().catch(() => ({}))) as { ids?: unknown };
  const ids = Array.isArray(body.ids) ? body.ids.filter((id): id is string => typeof id === "string") : undefined;

  const result = await api<{ unread_count: number }>("/auctioneer/notifications/read", {
    method: "POST",
    body: ids ? { ids } : {},
    token,
  });
  return NextResponse.json(result.ok ? result.data : result.problem, { status: result.status });
}
