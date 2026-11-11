import { Suspense } from "react";
import { redirect } from "next/navigation";

/** /minha-conta abre a primeira página da conta, levando os avisos da URL (boas-vindas, e-mail confirmado). */
export default function AccountIndex(props: PageProps<"/[slug]/minha-conta">) {
  return (
    <Suspense fallback={null}>
      <GoToFirstSection {...props} />
    </Suspense>
  );
}

async function GoToFirstSection({ params, searchParams }: PageProps<"/[slug]/minha-conta">): Promise<never> {
  const { slug } = await params;
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(await searchParams)) {
    if (typeof value === "string") query.set(key, value);
  }
  const qs = query.toString();
  redirect(`/${slug}/minha-conta/leiloes${qs ? `?${qs}` : ""}`);
}
