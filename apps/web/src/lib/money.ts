/** Valores em centavos (inteiros) ↔ texto em reais (RNF-13). */
const brl = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });

export function formatCents(cents: number): string {
  return brl.format(cents / 100);
}

/** "R$ 1.250,50" ou "1250,5" → 125050. Vazio → null. */
export function parseMoney(text: string): number | null {
  const digits = text.replace(/\D/g, "");
  return digits === "" ? null : Number(digits);
}

/** Máscara do campo de dinheiro: digita só números e vê "1.250,50". */
export function maskMoney(text: string): string {
  const cents = parseMoney(text);
  return cents === null ? "" : brl.format(cents / 100).replace(/^R\$\s?/, "");
}

export function centsToInput(cents: number | null | undefined): string {
  return cents === null || cents === undefined ? "" : brl.format(cents / 100).replace(/^R\$\s?/, "");
}
