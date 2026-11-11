import type { Auction, AuctionItem, Product, StoreCategory } from "@/lib/api-types";
import type { Featured } from "./home";
import type { TemplateKey } from "./kit";

/** Conteúdo de exemplo por modelo: o mesmo tipo de peça que a loja vende, para o layout fazer sentido. */
const SAMPLE: Record<TemplateKey, { auction: string; categories: string[]; items: [string, number, [string, string][]][] }> = {
  watches: {
    auction: "Relógios de Coleção",
    categories: ["Pulso", "Bolso", "Vintage", "Acessórios"],
    items: [
      ["Relógio automático 1985", 1_500_000, [["Calibre", "Automático"], ["Caixa", "36 mm"]]],
      ["Relógio de bolso em prata", 420_000, [["Material", "Prata 925"], ["Ano", "1920"]]],
      ["Cronógrafo de aço", 980_000, [["Calibre", "Manual"], ["Caixa", "42 mm"]]],
      ["Relógio de mesa francês", 260_000, [["Material", "Bronze"], ["Ano", "1890"]]],
      ["Relógio de pulso dourado", 1_250_000, [["Calibre", "Quartzo"], ["Caixa", "34 mm"]]],
      ["Relógio de bolso esmaltado", 610_000, [["Material", "Ouro 18k"], ["Ano", "1910"]]],
    ],
  },
  fashion: {
    auction: "Moda de Inverno",
    categories: ["Casacos", "Bolsas", "Vestidos", "Calçados"],
    items: [
      ["Blazer de lã italiana", 89_000, [["Tamanho", "M"], ["Cor", "Grafite"]]],
      ["Bolsa de couro estruturada", 120_000, [["Material", "Couro"], ["Cor", "Caramelo"]]],
      ["Casaco longo de alfaiataria", 145_000, [["Tamanho", "G"], ["Cor", "Preto"]]],
      ["Bolsa tiracolo vintage", 68_000, [["Material", "Couro"], ["Cor", "Vinho"]]],
      ["Blazer cruzado", 72_000, [["Tamanho", "P"], ["Cor", "Areia"]]],
      ["Clutch de festa", 45_000, [["Material", "Cetim"], ["Cor", "Prata"]]],
    ],
  },
  electronics: {
    auction: "Tecnologia em Oferta",
    categories: ["Celulares", "Áudio", "Notebooks", "Games"],
    items: [
      ["Smartphone 256 GB", 210_000, [["Armazenamento", "256 GB"], ["Estado", "Seminovo"]]],
      ["Fone sem fio com cancelamento", 65_000, [["Bateria", "30 h"], ["Conexão", "Bluetooth"]]],
      ["Smartphone 128 GB", 150_000, [["Armazenamento", "128 GB"], ["Estado", "Novo"]]],
      ["Fone de estúdio", 48_000, [["Driver", "40 mm"], ["Conexão", "P2"]]],
      ["Smartphone dobrável", 390_000, [["Armazenamento", "512 GB"], ["Estado", "Novo"]]],
      ["Caixa de som portátil", 32_000, [["Bateria", "12 h"], ["Proteção", "IP67"]]],
    ],
  },
};

export function sample(template: TemplateKey, startsAt: string) {
  const s = SAMPLE[template];
  const products: Product[] = s.items.map(([title, cents, attributes], i) => ({
    id: `p${i}`,
    slug: `p${i}`,
    title,
    description: null,
    category: null,
    reference_price_cents: null,
    starting_price_cents: cents,
    increment_cents: 10_000,
    youtube_video_id: null,
    youtube_url: null,
    attributes: attributes.map(([name, value]) => ({ name, value })),
    status: "available",
    views_count: 0,
    photos: [],
  }));
  const items: AuctionItem[] = products.slice(0, 4).map((p, i) => ({
    id: p.id,
    position: i + 1,
    title: p.title,
    status: "queued",
    starting_price_cents: p.starting_price_cents,
    increment_cents: p.increment_cents,
    current_price_cents: null,
    next_minimum_cents: p.starting_price_cents,
    bids_count: 0,
    leader: null,
    opened_at: null,
    ends_at: null,
    paused_remaining_ms: null,
    closed_at: null,
    sequence: 0,
    product: p,
  }));
  const auction = (id: string, name: string, offsetDays: number): Auction => ({
    id,
    slug: id,
    name,
    description: null,
    status: "scheduled",
    starts_at: new Date(Date.parse(startsAt) + offsetDays * 86_400_000).toISOString(),
    started_at: null,
    finished_at: null,
    settings: {},
    items_count: 8,
    cover: null,
    current_item_id: null,
  });
  const featured: Featured = { auction: { ...auction("a0", s.auction, 0), items }, items };
  const categories: StoreCategory[] = s.categories.map((name, i) => ({ id: `c${i}`, name, slug: `c${i}`, position: i, image_url: null }));
  return { products, featured, others: [auction("a1", "Leilão de Domingo", 2), auction("a2", "Leilão do Mês", 9)], categories };
}

