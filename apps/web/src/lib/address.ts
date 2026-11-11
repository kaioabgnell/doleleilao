/** Endereço de envio (loja e participante): campos `address.*` dos formulários. */

export const BRAZIL_STATES = [
  "AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA",
  "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO",
] as const;

const FIELDS = ["postal_code", "street", "number", "complement", "district", "city", "state"] as const;

/** Monta o `address` da API a partir do formulário. CEP vazio = `null` (apaga o endereço). */
export function addressFromForm(form: FormData): Record<(typeof FIELDS)[number], string> | null {
  const value = (key: string) => {
    const v = form.get(`address.${key}`);
    return typeof v === "string" ? v.trim() : "";
  };
  if (!value("postal_code")) return null;
  return Object.fromEntries(FIELDS.map((key) => [key, value(key)])) as Record<(typeof FIELDS)[number], string>;
}
