import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { connection } from "next/server";
import { Suspense } from "react";
import type { BidderAuctionSituation } from "@/lib/api-types";
import { getAuction } from "@/lib/auctions";
import { bidderApi, getBidder, getBidderToken } from "@/lib/bidder-session";
import { getStorefront } from "@/lib/storefront";
import { Auditorium } from "./auditorium";

export const metadata: Metadata = { title: "Leilão" };

/**
 * Página do leilão: antes de começar, detalhes e "Quero participar"; ao vivo, o auditório
 * (RF-F01 a RF-F04, RF-F09 a RF-F11); depois, o resultado. A estrutura vem do servidor e o tempo
 * real roda numa ilha no cliente.
 */
export default function AuctionPage(props: PageProps<"/[slug]/leilao/[leilao]">) {
  return (
    <Suspense fallback={<div className="mx-auto h-[36rem] w-full max-w-6xl animate-pulse px-4 py-6" aria-busy="true" />}>
      <AuctionContent {...props} />
    </Suspense>
  );
}

async function AuctionContent({ params }: PageProps<"/[slug]/leilao/[leilao]">) {
  // O auditório usa a hora atual (cronômetro): renderiza na hora da requisição, nunca no pré-render.
  await connection();
  const { slug, leilao } = await params;
  const data = await getAuction(slug, leilao);
  if (!data) notFound();

  const loggedIn = !!(await getBidderToken());
  const [situation, bidder] = loggedIn
    ? await Promise.all([bidderApi<BidderAuctionSituation>(`/bidders/me/auctions/${encodeURIComponent(leilao)}`), getBidder()])
    : [null, null];

  const me = situation?.ok ? situation.data : null;
  // Dados da loja já estão no cache (o layout usa os mesmos): só o WhatsApp para o ganhador.
  const store = await getStorefront(slug);
  const whatsapp = store.kind === "found" ? store.storefront.contact.whatsapp_url : null;

  return (
    // A chave inclui quem está logado: depois de entrar na conta, o auditório recomeça com a situação nova.
    <Auditorium
      key={`${data.auction.id}:${me?.bidder_id ?? "visitante"}`}
      slug={slug}
      initial={data}
      situation={me}
      bidderEmail={bidder?.email ?? null}
      whatsapp={whatsapp}
    />
  );
}
