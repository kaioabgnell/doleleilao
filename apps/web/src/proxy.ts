import { NextResponse, type NextRequest } from "next/server";
import { SESSION_COOKIE } from "./lib/session-cookie";

const API_URL = process.env.API_URL ?? "http://127.0.0.1:8000/api/v1";

/** Primeiro trecho de URL que é do próprio site, não de uma loja (ver StoreSlug::RESERVED na API). */
const SITE_ROUTES = new Set([
  "painel",
  "entrar",
  "cadastro",
  "esqueci-senha",
  "redefinir-senha",
  "sair",
  "design-system",
  "loja-nao-encontrada",
  "privacidade",
  "robots.txt",
  "sitemap.xml",
]);

/** Imagens de compartilhamento geradas pelo Next (`/opengraph-image-<hash>`): nunca são loja. */
const METADATA_ROUTE = /^(opengraph|twitter)-image(-[a-z0-9]+)?$/;

type StoreState = { state: "live" | "moved" | "missing"; slug: string | null };

/**
 * Respostas da API guardadas neste processo (o site roda num único servidor, D-31): 60 s para loja no ar
 * ou endereço antigo; só 5 s para "não encontrada", para uma loja recém-publicada abrir logo.
 */
const storeStates = new Map<string, { at: number; value: StoreState }>();
const TTL_MS = 60_000;
const MISSING_TTL_MS = 5_000;

async function storeState(slug: string): Promise<StoreState | null> {
  const cached = storeStates.get(slug);
  if (cached && Date.now() - cached.at < (cached.value.state === "missing" ? MISSING_TTL_MS : TTL_MS)) return cached.value;
  try {
    const res = await fetch(`${API_URL}/storefronts/${encodeURIComponent(slug)}/status`, {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(1500),
    });
    if (!res.ok) return null;
    const value = (await res.json()) as StoreState;
    if (storeStates.size > 2000) storeStates.clear();
    storeStates.set(slug, { at: Date.now(), value });
    return value;
  } catch {
    return null; // API fora: a própria página trata
  }
}

/**
 * 1. Painel: checagem otimista da sessão (presença do cookie); quem confirma o token é a API.
 * 2. Lojas (`/{slug}/…`): endereço antigo responde 301 para o novo (RF-A06) e loja inexistente
 *    responde 404 de verdade, antes de a página começar a ser enviada.
 */
export async function proxy(request: NextRequest) {
  const hasSession = request.cookies.has(SESSION_COOKIE);
  const { pathname, search } = request.nextUrl;

  if (pathname.startsWith("/painel") && !hasSession) {
    const url = new URL("/entrar", request.url);
    url.searchParams.set("voltar", pathname + search);
    return NextResponse.redirect(url);
  }

  if ((pathname === "/entrar" || pathname === "/cadastro") && hasSession) {
    return NextResponse.redirect(new URL("/painel", request.url));
  }

  const [, first = "", ...rest] = pathname.split("/");
  if (first && !SITE_ROUTES.has(first) && !METADATA_ROUTE.test(first) && /^[A-Za-z0-9-]{1,60}$/.test(first)) {
    const state = await storeState(first.toLowerCase());
    if (state?.state === "moved" && state.slug) {
      const url = new URL(`/${[state.slug, ...rest].join("/")}${search}`, request.url);
      return NextResponse.redirect(url, 301);
    }
    if (state?.state === "missing") {
      return NextResponse.rewrite(new URL("/loja-nao-encontrada", request.url), { status: 404 });
    }
    if (state?.state === "live" && state.slug && first !== state.slug) {
      return NextResponse.redirect(new URL(`/${[state.slug, ...rest].join("/")}${search}`, request.url), 301);
    }
  }

  return NextResponse.next();
}

export const config = {
  // Tudo menos arquivos, rotas internas do Next e a API do BFF.
  matcher: ["/((?!_next/|api/|brand/|favicon|icon|.*\\.[a-z0-9]+$).*)"],
};
