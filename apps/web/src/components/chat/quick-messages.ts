/**
 * Mensagens prontas do leiloeiro para o destaque do pregão: um toque envia. As primeiras mudam com o
 * momento (lance mínimo, próximo item); as outras são as falas de sempre do leilão.
 */
export interface QuickMessageContext {
  /** Lance mínimo do item em pregão, já formatado ("R$ 1.700,00"). */
  nextMinimum?: string | null;
  /** Título do próximo item da fila. */
  nextItemTitle?: string | null;
  /** Há item em pregão e nenhum depois dele. */
  lastItem?: boolean;
}

export function quickMessages({ nextMinimum, nextItemTitle, lastItem }: QuickMessageContext): string[] {
  const live: string[] = [];
  if (nextMinimum) live.push(`Mínimo agora: ${nextMinimum}. Quem dá mais?`);
  if (lastItem) live.push("Atenção! Este é o último item do lote.");
  if (nextItemTitle) live.push(`Próximo item: ${nextItemTitle.length > 28 ? `${nextItemTitle.slice(0, 27).trimEnd()}…` : nextItemTitle}`);
  return [
    ...live,
    "Olha o lance, pessoal!",
    "Vai encerrar! Última chance.",
    "Dou-lhe uma…",
    "Dou-lhe duas…",
    "Pausa rápida, já voltamos.",
    "Boa sorte a todos!",
    "Obrigado pela participação!",
  ];
}
