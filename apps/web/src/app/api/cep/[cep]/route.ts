import { NextResponse } from "next/server";
import { api } from "@/lib/api";
import type { PostalCodeResult } from "@/lib/api-types";

/** Endereço pelo CEP, para o formulário do painel e o da "Minha conta". 404 = CEP não existe. */
export async function GET(_: Request, { params }: RouteContext<"/api/cep/[cep]">) {
  const { cep } = await params;
  const result = await api<PostalCodeResult>(`/postal-codes/${encodeURIComponent(cep.replace(/\D/g, "").slice(0, 8))}`);
  return NextResponse.json(result.ok ? result.data : result.problem, {
    status: result.status,
    headers: result.ok ? { "Cache-Control": "private, max-age=86400" } : undefined,
  });
}
