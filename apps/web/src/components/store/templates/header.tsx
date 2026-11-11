import Link from "next/link";
import type { ReactNode } from "react";
import { Gavel, Search } from "lucide-react";
import type { TemplateKey } from "./kit";
import { Wrap } from "./kit";

export interface HeaderProps {
  template: TemplateKey;
  slug: string;
  name: string;
  logoUrl: string | null;
  categories: { name: string; slug: string }[];
  /** Entrar/Cadastre-se ou o avatar com o sino (a loja passa a versão do servidor; a prévia, uma imitação). */
  account: ReactNode;
  q?: string;
  /** Prévia no painel: fica dentro de outro formulário, então a busca não pode ser um <form>. */
  preview?: boolean;
}

function Brand({ name, logoUrl, href, size }: { name: string; logoUrl: string | null; href: string; size: "md" | "lg" }) {
  return (
    <Link href={href} className="flex min-h-11 min-w-0 items-center rounded-field focus-visible:outline-2 focus-visible:outline-store-primary">
      {logoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element -- logo já processada pela API
        <img src={logoUrl} alt={name} className={`w-auto object-contain ${size === "lg" ? "max-h-12 max-w-56" : "max-h-10 max-w-44"}`} />
      ) : (
        <span className={`font-store-heading truncate font-bold tracking-tight ${size === "lg" ? "text-2xl @3xl:text-3xl" : "text-xl"}`}>{name}</span>
      )}
    </Link>
  );
}

const navLink = "flex min-h-11 shrink-0 items-center px-3 transition-colors hover:text-store-link focus-visible:outline-2 focus-visible:outline-store-primary";

/** Topo da loja, um por modelo (D-07). */
export function StoreHeaderLayout(props: HeaderProps) {
  if (props.template === "fashion") return <FashionHeader {...props} />;
  if (props.template === "electronics") return <ElectronicsHeader {...props} />;
  return <WatchesHeader {...props} />;
}

/** Relógios: joalheria. Nome ao centro, navegação discreta, muito ar. */
function WatchesHeader({ slug, name, logoUrl, account }: HeaderProps) {
  const base = `/${slug}`;
  return (
    <header className="sticky top-0 z-20 border-b border-store-border bg-store-bg/95 backdrop-blur-sm">
      <Wrap className="grid h-16 grid-cols-[1fr_auto_1fr] items-center gap-3 @3xl:h-20">
        <nav aria-label="Loja" className="flex items-center text-[13px] font-medium tracking-[0.08em] uppercase">
          <Link href={`${base}/leiloes`} className={`${navLink} -ml-3`} aria-label="Leilões">
            <Gavel aria-hidden className="size-5 @3xl:hidden" />
            <span className="hidden @3xl:inline">Leilões</span>
          </Link>
          <Link href={`${base}#colecao`} className={`${navLink} hidden @3xl:flex`}>A coleção</Link>
          <Link href={`${base}/quero-leiloar`} className={`${navLink} hidden @5xl:flex`}>Quero leiloar</Link>
        </nav>
        <div className="flex justify-center">
          <Brand name={name} logoUrl={logoUrl} href={base} size="lg" />
        </div>
        <div className="flex justify-end">{account}</div>
      </Wrap>
    </header>
  );
}

/** Roupa: revista. Nome em caixa-alta à esquerda, categorias sempre à vista (no celular, numa faixa rolável). */
function FashionHeader({ slug, name, logoUrl, categories, account }: HeaderProps) {
  const base = `/${slug}`;
  const links = [{ href: `${base}/leiloes`, label: "Leilões" }, ...categories.slice(0, 6).map((c) => ({ href: `${base}/categoria/${c.slug}`, label: c.name }))];
  return (
    <header className="sticky top-0 z-20 bg-store-bg">
      <Wrap wide className="flex h-16 items-center justify-between gap-4 @3xl:h-[4.5rem]">
        <div className="flex min-w-0 items-center gap-6">
          <Brand name={name} logoUrl={logoUrl} href={base} size="md" />
          <nav aria-label="Categorias" className="hidden items-center text-sm font-medium @5xl:flex">
            {links.map((l) => (
              <Link key={l.href} href={l.href} className={navLink}>{l.label}</Link>
            ))}
          </nav>
        </div>
        {account}
      </Wrap>
      <nav aria-label="Categorias" className="border-y border-store-border @5xl:hidden">
        <Wrap wide className="flex gap-1 overflow-x-auto text-sm font-medium [scrollbar-width:none]">
          {links.map((l, i) => (
            <Link key={l.href} href={l.href} className={`${navLink} ${i === 0 ? "-ml-3" : ""}`}>{l.label}</Link>
          ))}
        </Wrap>
      </nav>
    </header>
  );
}

/** Eletrônicos: loja técnica. Busca em destaque e barra de categorias. */
function ElectronicsHeader({ slug, name, logoUrl, categories, account, q = "", preview = false }: HeaderProps) {
  const base = `/${slug}`;
  const input = "block min-h-11 w-full rounded-field border border-store-border bg-store-bg pr-3 pl-9 text-base text-store-text placeholder:text-store-text-muted focus:border-store-primary focus:outline-2 focus:outline-store-primary/30";
  const icon = <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-store-text-muted" />;
  const search = preview ? (
    <div className="relative w-full">
      {icon}
      <input type="search" readOnly tabIndex={-1} placeholder="Buscar produtos, marcas, modelos" className={input} />
    </div>
  ) : (
    <form role="search" action={base} className="relative w-full">
      <label htmlFor={`busca-${slug}`} className="sr-only">Buscar produtos</label>
      {icon}
      <input id={`busca-${slug}`} name="q" type="search" defaultValue={q} placeholder="Buscar produtos, marcas, modelos" className={input} />
    </form>
  );
  return (
    <header className="sticky top-0 z-20 border-b border-store-border bg-store-surface">
      <Wrap wide className="flex h-16 items-center gap-4">
        <Brand name={name} logoUrl={logoUrl} href={base} size="md" />
        <div className="hidden max-w-xl flex-1 @3xl:block">{search}</div>
        <div className="ml-auto">{account}</div>
      </Wrap>
      <Wrap wide className="pb-3 @3xl:hidden">{search}</Wrap>
      <nav aria-label="Categorias" className="border-t border-store-border">
        <Wrap wide className="flex gap-1 overflow-x-auto text-sm [scrollbar-width:none]">
          <Link href={`${base}/leiloes`} className={`${navLink} -ml-3 gap-1.5 font-semibold text-store-link`}>
            <Gavel aria-hidden className="size-4" />
            Leilões
          </Link>
          {categories.map((c) => (
            <Link key={c.slug} href={`${base}/categoria/${c.slug}`} className={`${navLink} text-store-text-muted hover:text-store-text`}>{c.name}</Link>
          ))}
          <Link href={`${base}/quero-leiloar`} className={`${navLink} ml-auto text-store-text-muted hover:text-store-text`}>Quero leiloar</Link>
        </Wrap>
      </nav>
    </header>
  );
}
