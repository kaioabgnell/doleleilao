import type { CSSProperties } from "react";
import type { Metadata } from "next";
import {
  allowedColorsFor,
  backgrounds,
  brand,
  ink,
  resolveStoreTheme,
  state,
  storeColors,
  storeFonts,
  templates,
  type BackgroundKey,
  type StoreColorKey,
  type TemplateKey,
} from "@doleleilao/design-tokens";

export const metadata: Metadata = { title: "Design system", robots: { index: false } };

/** Página interna da Fase 0: confere tokens, estados e os 3 modelos com a personalização padrão. */
export default function DesignSystemPage() {
  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-10">
      <h1 className="font-brand text-3xl font-bold text-dl-ink">Design system</h1>
      <p className="mt-2 text-dl-ink-muted">Tokens de @doleleilao/design-tokens (specs/07-design-system.md).</p>

      <Section title="Marca Dole Leilão">
        <Swatches
          items={[
            ["copper-400 · decorativo", brand.copper400],
            ["copper-500 · ícones", brand.copper500],
            ["copper-600 · botões", brand.copper600],
            ["copper-800 · hover", brand.copper800],
            ...Object.entries(ink).map(([k, v]) => [`ink-${k}`, v] as [string, string]),
          ]}
        />
        <div className="mt-4 flex flex-wrap gap-3">
          <button className="rounded-field bg-dl-copper px-5 py-3 font-semibold text-white hover:bg-dl-copper-hover">
            Botão primário
          </button>
          <span className="h-12 w-32 rounded-card" style={{ background: brand.gradient }} aria-hidden />
        </div>
      </Section>

      <Section title="Estados do leilão (fixos em todas as lojas)">
        <div className="flex flex-wrap gap-2">
          <Badge color={state.winning}>Você está ganhando</Badge>
          <Badge color={state.outbid}>Você foi superado</Badge>
          <Badge color={state.live}>
            <span className="mr-1.5 inline-block size-2 animate-pulse rounded-full bg-white" /> AO VIVO
          </Badge>
          <Badge color={state.closed}>Encerrado</Badge>
        </div>
        <p className="tabular mt-4 font-semibold text-dl-ink">
          <span className="text-4xl">R$ 12.450,00</span>
          <span className="ml-3 text-2xl text-dl-ink-muted">00:14</span>
        </p>
      </Section>

      <Section title="Os 3 modelos (personalização padrão)">
        <div className="grid gap-4 md:grid-cols-3">
          {(Object.keys(templates) as TemplateKey[]).map((key) => (
            <TemplatePreview key={key} template={key} />
          ))}
        </div>
      </Section>

      <Section title="Cores do leiloeiro permitidas por fundo">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[560px] text-sm">
            <tbody>
              {(Object.keys(backgrounds) as BackgroundKey[]).map((bg) => {
                const allowed = new Set(allowedColorsFor(bg));
                return (
                  <tr key={bg} className="border-t border-dl-line">
                    <th className="py-2 pr-3 text-left font-medium text-dl-ink">{backgrounds[bg].name}</th>
                    <td className="py-2">
                      <div className="flex gap-1.5 rounded-field p-2" style={{ background: backgrounds[bg].hex }}>
                        {(Object.keys(storeColors) as StoreColorKey[]).map((c) => (
                          <span
                            key={c}
                            title={`${storeColors[c].name}${allowed.has(c) ? "" : " (bloqueada neste fundo)"}`}
                            className="size-6 rounded-full"
                            style={{ background: storeColors[c].hex, opacity: allowed.has(c) ? 1 : 0.15 }}
                          />
                        ))}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Section>
    </main>
  );
}

function TemplatePreview({ template }: { template: TemplateKey }) {
  const t = templates[template];
  const vars = resolveStoreTheme({ template, ...t.defaults }) as CSSProperties;
  return (
    <article style={vars} className="overflow-hidden rounded-card border border-store-border bg-store-bg">
      <div className="p-4">
        <p className="text-xs uppercase tracking-widest text-store-text-muted">{t.name}</p>
        <h3 className="mt-1 font-store-heading text-2xl text-store-text">Lote de sábado</h3>
        <p className="mt-1 text-sm text-store-text-muted">
          {storeFonts[t.defaults.headingFont].name} · {storeColors[t.defaults.primary].name} ·{" "}
          {backgrounds[t.defaults.background].name}
        </p>
        <div className="mt-4 rounded-field border border-store-border bg-store-surface p-3">
          <p className="text-sm text-store-text-muted">Lance atual</p>
          <p className="tabular text-2xl font-semibold text-store-text">R$ 3.200,00</p>
          <button className="mt-3 w-full rounded-field bg-store-primary py-3 font-semibold text-store-on-primary hover:bg-store-primary-hover">
            Dar lance · R$ 3.300,00
          </button>
          <a href="#" className="mt-2 block text-center text-sm text-store-link underline">
            Configurar lance automático
          </a>
        </div>
      </div>
    </article>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="mt-10">
      <h2 className="mb-4 font-brand text-xl font-semibold text-dl-ink">{title}</h2>
      {children}
    </section>
  );
}

function Swatches({ items }: { items: [string, string][] }) {
  return (
    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-7">
      {items.map(([label, hex]) => (
        <li key={label} className="text-xs">
          <span className="block h-12 rounded-field border border-dl-line" style={{ background: hex }} />
          <span className="mt-1 block font-medium text-dl-ink">{label}</span>
          <span className="tabular text-dl-ink-muted">{hex}</span>
        </li>
      ))}
    </ul>
  );
}

function Badge({ color, children }: { color: string; children: React.ReactNode }) {
  return (
    <span className="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-semibold text-white" style={{ background: color }}>
      {children}
    </span>
  );
}
