import { BellRing, Gavel, Heart, Trophy, UserRound, type LucideIcon } from "lucide-react";

/** Páginas da "Minha conta" do participante: o mesmo menu no topo da loja e dentro da conta. */
export const accountSections: { path: string; label: string; icon: LucideIcon }[] = [
  { path: "leiloes", label: "Próximos leilões", icon: Gavel },
  { path: "arrematacoes", label: "Arrematações", icon: Trophy },
  { path: "dados", label: "Seus dados", icon: UserRound },
  { path: "alertas", label: "Alertas", icon: BellRing },
  { path: "favoritos", label: "Favoritos", icon: Heart },
];

export const accountHref = (slug: string, path: string) => `/${slug}/minha-conta/${path}`;
