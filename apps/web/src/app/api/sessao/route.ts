import { cookies } from "next/headers";
import { SESSION_COOKIE } from "@/lib/session-cookie";

/**
 * O site institucional (public/index.html) é estático: este endpoint diz só se há sessão do painel,
 * para o topo trocar "Entrar" por "Ir para o painel". Checagem otimista (presença do cookie).
 */
export async function GET() {
  const logged = (await cookies()).has(SESSION_COOKIE);
  return Response.json({ painel: logged }, { headers: { "Cache-Control": "private, no-store" } });
}
