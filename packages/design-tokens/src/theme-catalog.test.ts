import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import { contrast } from "./contrast.ts";
import { buildThemeCatalog, serializeThemeCatalog } from "./theme-catalog.ts";

const apiCopy = join(
  dirname(fileURLToPath(import.meta.url)),
  "../../../apps/api/resources/design/theme-catalog.json",
);

test("a cópia do catálogo usada pela API está atualizada", () => {
  assert.equal(
    readFileSync(apiCopy, "utf8"),
    serializeThemeCatalog(),
    "Rode `npm run build -w @doleleilao/design-tokens` para regenerar o catálogo da API.",
  );
});

test("o fundo padrão de cada modelo aceita as cores padrão", () => {
  const catalog = buildThemeCatalog();
  for (const [key, t] of Object.entries(catalog.templates)) {
    const allowed = catalog.backgrounds[t.defaults.background].allowedColors;
    assert.ok(t.backgrounds.includes(t.defaults.background), key);
    assert.ok(allowed.includes(t.defaults.primary), `${key}: primária`);
    assert.ok(allowed.includes(t.defaults.accent), `${key}: destaque`);
  }
});

test("e-mails da loja: botão com texto branco e link no fundo branco passam 4,5:1", () => {
  const catalog = buildThemeCatalog();
  for (const [key, c] of Object.entries(catalog.email)) {
    assert.ok(contrast(c.button, c.onButton) >= 4.5, `${key}: botão ${contrast(c.button, c.onButton).toFixed(2)}:1`);
    assert.ok(contrast(c.link, "#FFFFFF") >= 4.5, `${key}: link ${contrast(c.link, "#FFFFFF").toFixed(2)}:1`);
  }
});
