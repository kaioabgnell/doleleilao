import { redirect } from "next/navigation";

/** Configurações ainda tem uma página só: Pagamento. */
export default function SettingsPage() {
  redirect("/painel/configuracoes/pagamento");
}
