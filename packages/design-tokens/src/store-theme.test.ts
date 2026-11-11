import { test } from "node:test";
import assert from "node:assert/strict";
import { contrast, WCAG } from "./contrast.ts";
import { allowedColorsFor, resolveStoreTheme, validateStoreTheme } from "./store-theme.ts";
import {
  backgrounds,
  brand,
  highlight,
  ink,
  state,
  storeColors,
  storeFonts,
  templates,
  type BackgroundKey,
  type StoreColorKey,
  type StoreFontKey,
  type TemplateKey,
} from "./tokens.ts";

const colorKeys = Object.keys(storeColors) as StoreColorKey[];

test("as 10 cores da loja passam 4,5:1 com texto branco por cima", () => {
  assert.equal(colorKeys.length, 10);
  for (const key of colorKeys) {
    assert.ok(contrast("#FFFFFF", storeColors[key].hex) >= WCAG.text, key);
  }
});

test("cores da marca e de estado usadas com texto branco passam 4,5:1", () => {
  for (const hex of [brand.copper600, brand.copper800, state.winning, state.outbid, state.closed]) {
    assert.ok(contrast("#FFFFFF", hex) >= WCAG.text, hex);
  }
  assert.ok(contrast(ink[900], "#F6F5F2") >= WCAG.text);
});

test("palco escuro da tela de entrada: texto, texto secundário e lime passam 4,5:1 (D-53)", () => {
  for (const bg of [ink[900], ink[950]]) {
    for (const fg of [ink[50], ink[300], highlight.lime]) {
      assert.ok(contrast(fg, bg) >= WCAG.text, `${fg} sobre ${bg}`);
    }
  }
  // O lime nunca vai no fundo claro.
  assert.ok(contrast(highlight.lime, "#F6F5F2") < 3);
});

test("o cobre claro da logo NÃO serve para texto (só decorativo)", () => {
  assert.ok(contrast("#FFFFFF", brand.copper400) < WCAG.text);
});

test("no fundo Ébano só Cobre e Dourado-escuro podem ser primária/destaque", () => {
  assert.deepEqual(allowedColorsFor("ebony").sort(), ["copper", "dark-gold"]);
});

test("nos fundos claros todas as 10 cores são permitidas", () => {
  for (const bg of ["white", "ivory", "sand", "mist", "ice", "light-graphite"] as BackgroundKey[]) {
    assert.equal(allowedColorsFor(bg).length, 10, bg);
  }
});

test("fundo fora do modelo é recusado", () => {
  const issues = validateStoreTheme({
    template: "fashion",
    primary: "black",
    accent: "wine",
    background: "ebony",
    headingFont: "inter",
  });
  assert.ok(issues.some((i) => i.code === "background-not-in-template"));
});

test("toda combinação permitida gera tema com texto e link legíveis (matriz completa)", () => {
  let checked = 0;
  for (const template of Object.keys(templates) as TemplateKey[]) {
    for (const background of templates[template].backgrounds as readonly BackgroundKey[]) {
      const allowed = allowedColorsFor(background);
      for (const primary of allowed) {
        for (const accent of allowed) {
          for (const headingFont of Object.keys(storeFonts) as StoreFontKey[]) {
            const vars = resolveStoreTheme({ template, primary, accent, background, headingFont });
            const bg = backgrounds[background].hex;
            assert.ok(contrast(vars["--store-text"], bg) >= WCAG.text);
            assert.ok(contrast(vars["--store-link"], bg) >= WCAG.text, `${template}/${background}/${primary}`);
            assert.ok(contrast(vars["--store-on-primary"], vars["--store-primary"]) >= WCAG.text);
            checked++;
          }
        }
      }
    }
  }
  assert.ok(checked > 0);
});

test("os padrões de cada modelo são válidos", () => {
  for (const [key, t] of Object.entries(templates)) {
    assert.deepEqual(validateStoreTheme({ template: key as TemplateKey, ...t.defaults }), [], key);
  }
});
