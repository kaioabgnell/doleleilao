import Link from "next/link";
import type { PublicStorefront, SocialKey } from "@/lib/api-types";

const socialLabels: Record<SocialKey, string> = {
  instagram: "Instagram",
  facebook: "Facebook",
  tiktok: "TikTok",
  youtube: "YouTube",
  website: "Site",
};

/** Rodapé da loja: contato do leiloeiro + "desenvolvido por Doleleilao.com.br" com UTM (D-06, D-20). */
export function StoreFooter({ storefront }: { storefront: PublicStorefront }) {
  const { contact } = storefront;
  const socials = (Object.keys(socialLabels) as SocialKey[]).filter((key) => contact.socials[key]);

  return (
    <footer className="mt-8 border-t border-store-border bg-store-surface">
      <div className={`mx-auto flex w-full flex-col gap-4 px-4 py-6 text-sm @3xl:flex-row @3xl:items-start @3xl:justify-between @3xl:px-6 ${storefront.theme.template === "watches" ? "max-w-[72rem]" : "max-w-[80rem]"}`}>
        <nav aria-label="Loja" className="flex flex-col gap-1 sm:order-last">
          <a href={`/${storefront.slug}/leiloes`} className="flex min-h-11 items-center text-store-link underline-offset-4 hover:underline sm:min-h-0">Leilões</a>
          <a href={`/${storefront.slug}/quero-leiloar`} className="flex min-h-11 items-center text-store-link underline-offset-4 hover:underline sm:min-h-0">Quer leiloar algo seu?</a>
          <a href={`/${storefront.slug}/minha-conta`} className="flex min-h-11 items-center text-store-link underline-offset-4 hover:underline sm:min-h-0">Minha conta</a>
          <a href={`/${storefront.slug}/termos`} className="flex min-h-11 items-center text-store-link underline-offset-4 hover:underline sm:min-h-0">Termos de uso</a>
          <Link href="/privacidade" className="flex min-h-11 items-center text-store-link underline-offset-4 hover:underline sm:min-h-0">Política de privacidade</Link>
        </nav>
        <div className="flex flex-col gap-1">
          <p className="font-store-heading text-base font-bold">{storefront.display_name}</p>
          {contact.whatsapp && contact.whatsapp_url ? (
            <a href={contact.whatsapp_url} target="_blank" rel="noreferrer" className="text-store-link underline-offset-4 hover:underline">
              WhatsApp {contact.whatsapp}
            </a>
          ) : null}
          {contact.email ? (
            <a href={`mailto:${contact.email}`} className="text-store-link underline-offset-4 hover:underline">
              {contact.email}
            </a>
          ) : null}
        </div>
        {socials.length > 0 ? (
          <ul className="flex flex-wrap gap-x-4 gap-y-1">
            {socials.map((key) => (
              <li key={key}>
                <a href={contact.socials[key]} target="_blank" rel="noreferrer" className="text-store-link underline-offset-4 hover:underline">
                  {socialLabels[key]}
                </a>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
      <p className="border-t border-store-border px-4 py-3 text-center text-xs text-store-text-muted">
        desenvolvido por{" "}
        <a
          href={`https://doleleilao.com.br/?utm_source=${encodeURIComponent(storefront.slug)}&utm_medium=footer`}
          className="text-store-link underline-offset-4 hover:underline"
        >
          Doleleilao.com.br
        </a>
      </p>
    </footer>
  );
}
