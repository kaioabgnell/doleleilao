import type { Metadata } from "next";
import { Suspense } from "react";
import { AlertsForm } from "../account-forms";
import { AccountSection, SectionSkeleton, requireBidder } from "../shared";

export const metadata: Metadata = { title: "Alertas", robots: { index: false } };

export default function AlertsPage(props: PageProps<"/[slug]/minha-conta/alertas">) {
  return (
    <Suspense fallback={<SectionSkeleton />}>
      <Alerts {...props} />
    </Suspense>
  );
}

async function Alerts({ params }: PageProps<"/[slug]/minha-conta/alertas">) {
  const { slug } = await params;
  const bidder = await requireBidder(slug, "alertas");

  return (
    <AccountSection title="Alertas por e-mail" description="Avisamos antes de começar os leilões em que você marcou “Quero participar” ou tem produtos favoritos.">
      <div className="rounded-card border border-store-border bg-store-surface p-5 sm:p-6">
        <AlertsForm minutes={bidder.alert_minutes} />
      </div>
    </AccountSection>
  );
}
