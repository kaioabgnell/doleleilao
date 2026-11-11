import type { Metadata } from "next";
import Link from "next/link";
import { RegisterForm } from "./register-form";

export const metadata: Metadata = { title: "Crie sua loja de leilões" };

export default function RegisterPage() {
  return (
    <>
      <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Crie sua loja de leilões</h1>
      <p className="mt-2 text-dl-ink-muted">Leva uns 3 minutos. Depois, o painel mostra os próximos passos.</p>
      <div className="mt-6 rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
        <RegisterForm />
      </div>
      <p className="mt-6 text-center text-sm text-dl-ink-muted">
        Já tem conta?{" "}
        <Link href="/entrar" className="font-semibold text-dl-copper underline-offset-4 hover:underline">
          Entrar
        </Link>
      </p>
    </>
  );
}
