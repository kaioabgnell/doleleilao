import type { ReactNode } from "react";

type Block = { kind: "heading"; text: string } | { kind: "list"; items: string[] } | { kind: "paragraph"; lines: string[] };

/** Lê o texto simples dos termos: "## " abre um título, "- " um item de lista, linha em branco separa parágrafos. */
export function parseTerms(text: string): Block[] {
  const blocks: Block[] = [];
  for (const raw of text.replace(/\r\n?/g, "\n").split("\n")) {
    const line = raw.trim();
    const last = blocks.at(-1);
    if (line === "") {
      blocks.push({ kind: "paragraph", lines: [] });
    } else if (line.startsWith("## ")) {
      blocks.push({ kind: "heading", text: line.slice(3).trim() });
    } else if (line.startsWith("- ")) {
      if (last?.kind === "list") last.items.push(line.slice(2).trim());
      else blocks.push({ kind: "list", items: [line.slice(2).trim()] });
    } else if (last?.kind === "paragraph") {
      last.lines.push(line);
    } else {
      blocks.push({ kind: "paragraph", lines: [line] });
    }
  }
  return blocks.filter((b) => b.kind !== "paragraph" || b.lines.length > 0);
}

/**
 * Texto de termos e políticas, com a mesma leitura na loja (cores da loja) e no site da plataforma.
 * Linha de leitura curta (65 caracteres) e títulos que aparecem no sumário do leitor de tela.
 */
export function TermsText({ text, tone }: { text: string; tone: "store" | "platform" }) {
  const heading = tone === "store" ? "font-store-heading text-store-text" : "font-brand text-dl-ink";
  const body = tone === "store" ? "text-store-text" : "text-dl-ink";
  const marker = tone === "store" ? "bg-store-primary" : "bg-dl-copper";

  return (
    <div className={`flex max-w-[65ch] flex-col gap-4 text-base leading-relaxed ${body}`}>
      {parseTerms(text).map((block, i): ReactNode => {
        if (block.kind === "heading") {
          return (
            <h2 key={i} className={`mt-4 text-xl font-bold first:mt-0 ${heading}`}>
              {block.text}
            </h2>
          );
        }
        if (block.kind === "list") {
          return (
            <ul key={i} className="flex flex-col gap-2">
              {block.items.map((item, j) => (
                <li key={j} className="flex gap-3">
                  <span aria-hidden className={`mt-[0.6em] size-1.5 shrink-0 rounded-full ${marker}`} />
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          );
        }
        return (
          <p key={i}>
            {block.lines.map((line, j) => (
              <span key={j}>
                {j > 0 ? <br /> : null}
                {line}
              </span>
            ))}
          </p>
        );
      })}
    </div>
  );
}
