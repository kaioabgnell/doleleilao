import type { Metadata } from "next";
import Link from "next/link";
import { ForgotForm } from "./forgot-form";

export const metadata: Metadata = { title: "Esqueci minha senha" };

export default function ForgotPasswordPage() {
  return (
    <>
      <h1 className="font-brand text-2xl font-bold text-dl-ink sm:text-3xl">Esqueci minha senha</h1>
      <p className="mt-2 text-dl-ink-muted">Informe o e-mail da sua conta. Vamos mandar um link para criar uma nova senha.</p>
      <div className="mt-6 rounded-card border border-dl-line bg-dl-card p-5 sm:p-6">
        <ForgotForm />
      </div>
      <p className="mt-6 text-center text-sm">
        <Link href="/entrar" className="font-semibold text-dl-copper underline-offset-4 hover:underline">
          Voltar para o login
        </Link>
      </p>
    </>
  );
}
