import { NextResponse, type NextRequest } from "next/server";
import { api } from "@/lib/api";
import type { SlugAvailability } from "@/lib/api-types";
import { getToken } from "@/lib/session";

/** Checagem ao vivo do endereço da loja (cadastro e troca de endereço no painel). */
export async function GET(request: NextRequest) {
  const slug = request.nextUrl.searchParams.get("slug") ?? "";
  const result = await api<SlugAvailability>(
    `/auctioneers/slug-availability?slug=${encodeURIComponent(slug)}`,
    { token: await getToken() },
  );
  return NextResponse.json(result.ok ? result.data : result.problem, { status: result.status });
}
