/** Máscaras dos formulários. A API aceita com ou sem máscara e guarda só os caracteres. */

export function onlyDigits(value: string): string {
  return value.replace(/\D/g, "");
}

/** (11) 98765-4321 ou (11) 3456-7890 */
export function maskPhone(value: string): string {
  const d = onlyDigits(value).slice(0, 11);
  if (d.length <= 2) return d.length ? `(${d}` : "";
  const local = d.slice(2);
  const split = local.length > 8 ? 5 : 4;
  if (local.length <= split) return `(${d.slice(0, 2)}) ${local}`;
  return `(${d.slice(0, 2)}) ${local.slice(0, split)}-${local.slice(split)}`;
}

/** 00000-000 */
export function maskPostalCode(value: string): string {
  const d = onlyDigits(value).slice(0, 8);
  return d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
}

/** 000.000.000-00 */
export function maskCpf(value: string): string {
  const d = onlyDigits(value).slice(0, 11);
  return d
    .replace(/^(\d{3})(\d)/, "$1.$2")
    .replace(/^(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
    .replace(/\.(\d{3})(\d{1,2})$/, ".$1-$2");
}

/** 00.000.000/0000-00, aceitando letras nos 12 primeiros (CNPJ alfanumérico, desde 07/2026). */
export function maskCnpj(value: string): string {
  const raw = value.toUpperCase().replace(/[^0-9A-Z]/g, "");
  const head = raw.slice(0, 12);
  const tail = raw.slice(12).replace(/\D/g, "").slice(0, 2);
  const c = head + tail;
  const parts = [c.slice(0, 2), c.slice(2, 5), c.slice(5, 8), c.slice(8, 12), c.slice(12, 14)];
  let out = parts[0];
  if (parts[1]) out += `.${parts[1]}`;
  if (parts[2]) out += `.${parts[2]}`;
  if (parts[3]) out += `/${parts[3]}`;
  if (parts[4]) out += `-${parts[4]}`;
  return out;
}

export function maskDocument(type: "cpf" | "cnpj", value: string): string {
  return type === "cpf" ? maskCpf(value) : maskCnpj(value);
}

/** Pré-visualização do endereço: a-z, 0-9 e hífen. */
export function slugify(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9-]+/g, "-")
    .replace(/-{2,}/g, "-")
    .replace(/^-/, "")
    .slice(0, 40);
}

export function formatDate(iso: string): string {
  return new Intl.DateTimeFormat("pt-BR", { dateStyle: "long", timeZone: "America/Sao_Paulo" }).format(new Date(iso));
}
