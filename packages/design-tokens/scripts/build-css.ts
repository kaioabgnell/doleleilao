/**
 * Gera dist/tokens.css a partir de src/tokens.ts e o catálogo de temas usado pela API.
 * Uso: node scripts/build-css.ts   (Node >= 22.18 roda TypeScript direto)
 */
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { resolveStoreTheme } from "../src/store-theme.ts";
import { serializeThemeCatalog } from "../src/theme-catalog.ts";
import { brand, highlight, ink, radius, space, state, surface, templates } from "../src/tokens.ts";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

const vars: Record<string, string> = {
  "--dl-copper-400": brand.copper400,
  "--dl-copper-500": brand.copper500,
  "--dl-copper-600": brand.copper600,
  "--dl-copper-800": brand.copper800,
  "--dl-gradient": brand.gradient,
  ...Object.fromEntries(Object.entries(ink).map(([k, v]) => [`--dl-ink-${k}`, v])),
  "--dl-lime": highlight.lime,
  "--dl-surface-page": surface.page,
  "--dl-surface-card": surface.card,
  "--state-winning": state.winning,
  "--state-outbid": state.outbid,
  "--state-live": state.live,
  "--state-closed": state.closed,
  "--state-on": state.onState,
  ...Object.fromEntries(space.map((v, i) => [`--space-${i}`, `${v}px`])),
  "--radius-field": `${radius.field}px`,
  "--radius-card": `${radius.card}px`,
  "--radius-pill": `${radius.pill}px`,
  // Loja: valores padrão (modelo Relógios). O layout de cada loja sobrescreve em tempo de execução.
  ...resolveStoreTheme({ template: "watches", ...templates.watches.defaults }),
};

const css =
  "/* Gerado por packages/design-tokens/scripts/build-css.ts — não editar à mão. */\n:root {\n" +
  Object.entries(vars)
    .map(([k, v]) => `  ${k}: ${v};`)
    .join("\n") +
  "\n}\n";

mkdirSync(join(root, "dist"), { recursive: true });
writeFileSync(join(root, "dist/tokens.css"), css);
console.log(`dist/tokens.css: ${Object.keys(vars).length} variáveis`);

// Catálogo de temas para a API validar a personalização com as mesmas regras (theme-catalog.ts).
const catalogPath = join(root, "../../apps/api/resources/design/theme-catalog.json");
mkdirSync(dirname(catalogPath), { recursive: true });
writeFileSync(catalogPath, serializeThemeCatalog());
console.log("apps/api/resources/design/theme-catalog.json: atualizado");
