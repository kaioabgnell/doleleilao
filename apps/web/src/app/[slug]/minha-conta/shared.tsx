import type { ReactNode } from "react";
import { redirect } from "next/navigation";
import type { Bidder } from "@/lib/api-types";
import { getBidder } from "@/lib/bidder-session";

/** Participante logado, ou volta para o login trazendo a pessoa de volta a esta página depois. */
export async function requireBidder(slug: string, path: string): Promise<Bidder> {
  const bidder = await getBidder();
  if (!bidder) redirect(`/${slug}/entrar?voltar=${encodeURIComponent(`/${slug}/minha-conta/${path}`)}`);
  return bidder;
}

export function AccountSection({ title, description, children }: { title: string; description?: ReactNode; children: ReactNode }) {
  return (
    <section aria-labelledby="secao-titulo" className="flex flex-col gap-4">
      <div>
        <h2 id="secao-titulo" className="font-store-heading text-xl font-bold sm:text-2xl">
          {title}
        </h2>
        {description ? <p className="mt-1 text-sm text-store-text-muted">{description}</p> : null}
      </div>
      {children}
    </section>
  );
}

export function EmptyState({ children }: { children: ReactNode }) {
  return <div className="rounded-card border border-dashed border-store-border bg-store-surface p-8 text-center text-store-text-muted">{children}</div>;
}

export function SectionSkeleton() {
  return (
    <div aria-busy="true" className="flex flex-col gap-4">
      <span className="h-7 w-48 animate-pulse rounded-field bg-store-border/50" />
      <span className="h-40 animate-pulse rounded-card bg-store-border/40" />
    </div>
  );
}
