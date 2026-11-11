import type { NextRequest } from "next/server";

/** Route Handlers que mudam dados só aceitam chamadas do próprio site (além do cookie SameSite=Lax). */
export function isSameOrigin(request: NextRequest): boolean {
  const origin = request.headers.get("origin");
  return origin === null || origin === request.nextUrl.origin;
}
