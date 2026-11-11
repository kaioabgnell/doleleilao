import { NextResponse, type NextRequest } from "next/server";
import { api } from "@/lib/api";
import type { Suggestion } from "@/lib/api-types";

/** Endereço livre e nome de exibição sugeridos a partir do nome da empresa (RF-A02, RF-A03). */
export async function GET(request: NextRequest) {
  const company = request.nextUrl.searchParams.get("empresa") ?? "";
  const result = await api<Suggestion>(`/auctioneers/suggestions?company_name=${encodeURIComponent(company)}`);
  return NextResponse.json(result.ok ? result.data : result.problem, { status: result.status });
}
