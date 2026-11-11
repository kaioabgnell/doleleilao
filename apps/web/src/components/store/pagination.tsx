import Link from "next/link";

export function Pagination({ base, page, lastPage, query = {} }: { base: string; page: number; lastPage: number; query?: Record<string, string> }) {
  if (lastPage <= 1) return null;
  const href = (p: number) => `${base}?${new URLSearchParams({ ...query, ...(p > 1 ? { pagina: String(p) } : {}) })}`;
  const cls = "inline-flex min-h-11 items-center rounded-field border border-store-border px-4 text-sm font-medium hover:border-store-primary";
  return (
    <nav aria-label="Páginas" className="flex items-center justify-center gap-2">
      {page > 1 ? <Link href={href(page - 1)} className={cls}>Anterior</Link> : null}
      <span className="px-2 text-sm text-store-text-muted tabular">{page} de {lastPage}</span>
      {page < lastPage ? <Link href={href(page + 1)} className={cls}>Próxima</Link> : null}
    </nav>
  );
}
