/**
 * Cartão do leiloeiro (Configurações → Pagamento, D-54): máscara e bandeira enquanto digita.
 * Quem decide é a API (apps/api/src/Billing/Domain/CreditCard.php); aqui é só para ajudar a digitar.
 */
import type { CardBrand, FeeTier } from "./api-types";
import { formatCents } from "./money";

/** Mesmas faixas da API (CardBrand::ELO): a Elo também começa com 4, 5 e 6. */
const ELO =
  /^(40117[89]|431274|438935|451416|457393|45763[12]|504175|506699|5067[0-6]\d|50677[0-8]|509\d{3}|627780|636297|636368|65003[1-3]|65003[5-9]|65004\d|65005[01]|65040[5-9]|6504[1-3]\d|65048[5-9]|65049\d|6505[0-2]\d|65053[0-8]|65054[1-9]|6505[5-8]\d|65059[0-8]|65070\d|65071[0-8]|65072[0-7]|65090[1-9]|65091\d|650920|65165[2-9]|6516[67]\d|6550[01]\d|65502[1-9]|6550[34]\d|65505[0-8])/;

export const BRAND_LABEL: Record<CardBrand, string> = {
  visa: "Visa",
  mastercard: "Mastercard",
  elo: "Elo",
  amex: "American Express",
  hipercard: "Hipercard",
  diners: "Diners Club",
};

export function detectBrand(digits: string): CardBrand | null {
  if (ELO.test(digits)) return "elo";
  if (/^(606282|3841)/.test(digits)) return "hipercard";
  if (/^3[47]/.test(digits)) return "amex";
  if (/^3(0[0-5]|[689])/.test(digits)) return "diners";
  if (/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/.test(digits)) return "mastercard";
  if (digits.startsWith("4")) return "visa";
  return null;
}

function maxDigits(brand: CardBrand | null): number {
  if (brand === "amex") return 15;
  if (brand === "mastercard" || brand === "elo") return 16;
  return 19;
}

/** "4111111111111111" → "4111 1111 1111 1111"; Amex e Diners em 4-6-5 / 4-6-4. */
export function maskCardNumber(text: string): string {
  const brand = detectBrand(text.replace(/\D/g, ""));
  const digits = text.replace(/\D/g, "").slice(0, maxDigits(brand));
  if (brand === "amex" || (brand === "diners" && digits.length <= 14)) {
    return [digits.slice(0, 4), digits.slice(4, 10), digits.slice(10)].filter(Boolean).join(" ");
  }
  return digits.replace(/(\d{4})(?=\d)/g, "$1 ");
}

/** "1230" → "12/30". */
export function maskExpiry(text: string): string {
  const digits = text.replace(/\D/g, "").slice(0, 4);
  return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
}

export function cvvLength(brand: CardBrand | null): number {
  return brand === "amex" ? 4 : 3;
}

/** 750 → "7,5%". */
export function formatRate(bps: number): string {
  return `${(bps / 100).toLocaleString("pt-BR", { maximumFractionDigits: 2 })}%`;
}

/** "Até R$ 100,00", "R$ 100,01 a R$ 500,00", "Acima de R$ 1.000,00". */
export function tierRange(tier: FeeTier): string {
  if (tier.max_cents === null) return `Acima de ${formatCents(tier.min_cents - 1)}`;
  if (tier.min_cents === 0) return `Até ${formatCents(tier.max_cents)}`;
  return `${formatCents(tier.min_cents)} a ${formatCents(tier.max_cents)}`;
}
