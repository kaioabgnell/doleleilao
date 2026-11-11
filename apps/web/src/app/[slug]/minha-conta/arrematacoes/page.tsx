import type { Metadata } from "next";
import { Suspense } from "react";
import type { BidderAward } from "@/lib/api-types";
import { bidderApi } from "@/lib/bidder-session";
import { formatCents } from "@/lib/money";
import { AccountSection, EmptyState, SectionSkeleton, requireBidder } from "../shared";

export const metadata: Metadata = { title: "Arrematações", robots: { index: false } };

const awardTone: Record<BidderAward["status"], string> = {
  awaiting_contact: "bg-store-bg text-store-text",
  negotiating: "bg-store-primary/10 text-store-link",
  paid: "bg-state-winning/10 text-state-winning",
  delivered: "bg-state-closed/10 text-state-closed",
  withdrawn: "bg-store-bg text-store-text-muted",
};

const dateTime = new Intl.DateTimeFormat("pt-BR", { timeZone: "America/Sao_Paulo", day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });

export default function AwardsPage(props: PageProps<"/[slug]/minha-conta/arrematacoes">) {
  return (
    <Suspense fallback={<SectionSkeleton />}>
      <Awards {...props} />
    </Suspense>
  );
}

async function Awards({ params }: PageProps<"/[slug]/minha-conta/arrematacoes">) {
  const { slug } = await params;
  await requireBidder(slug, "arrematacoes");
  const awards = await bidderApi<{ data: BidderAward[] }>("/bidders/me/awards");
  const list = awards.ok ? awards.data.data : [];

  return (
    <AccountSection title="Arrematações" description="Os itens que você arrematou, com a situação e as mensagens da loja.">
      {list.length > 0 ? (
        <ul className="flex flex-col gap-3">
          {list.map((award) => (
            <li key={award.id} className="flex flex-col gap-3 rounded-card border border-store-border bg-store-surface p-4">
              <div className="flex gap-3">
                {award.item.thumb ? (
                  // eslint-disable-next-line @next/next/no-img-element -- miniatura já processada
                  <img src={award.item.thumb} alt="" className="size-16 shrink-0 rounded-field object-cover" />
                ) : null}
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">{award.item.title}</p>
                  <p className="text-sm text-store-text-muted">
                    {award.auction.name} · <span className="tabular">{dateTime.format(new Date(award.awarded_at))}</span>
                  </p>
                  <p className="mt-1 flex flex-wrap items-center gap-2">
                    <strong className="tabular">{formatCents(award.amount_cents)}</strong>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${awardTone[award.status]}`}>{award.status_label}</span>
                  </p>
                </div>
              </div>
              {award.messages.length > 0 ? (
                <ol className="flex flex-col gap-2 border-t border-store-border pt-3">
                  {award.messages.map((m) => (
                    <li key={m.id} className="rounded-field bg-store-bg px-3 py-2 text-sm">
                      <p className="mb-1 text-xs text-store-text-muted tabular">Mensagem da loja · {dateTime.format(new Date(m.sent_at))}</p>
                      <p className="whitespace-pre-line">{m.body}</p>
                    </li>
                  ))}
                </ol>
              ) : (
                <p className="border-t border-store-border pt-3 text-sm text-store-text-muted">O leiloeiro vai entrar em contato para combinar o pagamento e a entrega.</p>
              )}
            </li>
          ))}
        </ul>
      ) : (
        <EmptyState>Quando você arrematar um item, ele aparece aqui com as mensagens da loja.</EmptyState>
      )}
    </AccountSection>
  );
}
