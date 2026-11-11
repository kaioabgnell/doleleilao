import { revalidateTag } from "next/cache";
import { NextResponse, type NextRequest } from "next/server";
import { timingSafeEqual } from "node:crypto";

/**
 * Chamado pela API quando algo visível na loja muda: descarta o cache das tags informadas.
 * Protegido por segredo compartilhado (REVALIDATE_SECRET).
 */
export async function POST(request: NextRequest) {
  const secret = process.env.REVALIDATE_SECRET ?? "";
  const given = request.headers.get("x-revalidate-secret") ?? "";
  const valid =
    secret.length > 0 && given.length === secret.length && timingSafeEqual(Buffer.from(given), Buffer.from(secret));
  if (!valid) return NextResponse.json({ ok: false }, { status: 401 });

  const body = (await request.json().catch(() => ({}))) as { tags?: unknown };
  const tags = Array.isArray(body.tags)
    ? body.tags.filter((t): t is string => typeof t === "string" && /^storefront:[a-z0-9-]{1,60}$/.test(t))
    : [];

  // O leiloeiro quer ver a mudança na hora: sem servir conteúdo antigo.
  for (const tag of tags) revalidateTag(tag, { expire: 0 });

  return NextResponse.json({ ok: true, tags });
}
