"use client";

import { useSearchParams } from "next/navigation";
import { StoreNotice } from "@/components/store/store-ui";

const emailNotices: Record<string, [tone: "success" | "error", text: string]> = {
  verificado: ["success", "E-mail confirmado! Seu cadastro está completo."],
  "link-expirado": ["error", "Este link expirou. Peça um novo em “Seus dados”."],
  "link-invalido": ["error", "Este link não é válido. Peça um novo em “Seus dados”."],
};

/** Avisos que chegam pela URL: boas-vindas depois do cadastro e o resultado da confirmação do e-mail. */
export function AccountNotices({ nickname }: { nickname: string }) {
  const params = useSearchParams();
  const email = emailNotices[params.get("email") ?? ""];
  if (email) return <StoreNotice tone={email[0]}>{email[1]}</StoreNotice>;
  if (params.has("bem-vindo")) return <StoreNotice tone="success">Boas-vindas, {nickname}! Sua conta foi criada.</StoreNotice>;
  return null;
}
