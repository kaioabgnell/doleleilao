import type { ReactNode } from "react";

export function AuthShell({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
  return (
    <main className="mx-auto w-full max-w-md px-4 py-8 sm:py-12">
      <h1 className="font-store-heading text-2xl font-bold sm:text-3xl">{title}</h1>
      {subtitle ? <p className="mt-2 text-store-text-muted">{subtitle}</p> : null}
      <div className="mt-6 rounded-card border border-store-border bg-store-surface p-5 sm:p-6">{children}</div>
    </main>
  );
}

export function param(value: string | string[] | undefined): string | undefined {
  return typeof value === "string" && value.length < 300 ? value : undefined;
}
