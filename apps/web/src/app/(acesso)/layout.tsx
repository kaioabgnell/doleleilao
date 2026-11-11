import Image from "next/image";
import Link from "next/link";

/** Telas de acesso do leiloeiro: marca Dole Leilão (D-01), mobile first. */
export default function AccessLayout({ children }: LayoutProps<"/">) {
  return (
    <div className="flex flex-1 flex-col items-center px-4 py-8 sm:py-14">
      <Link href="/" aria-label="Dole Leilão, página inicial" className="rounded-field">
        <Image src="/brand/logo-horizontal.svg" alt="Dole Leilão" width={168} height={64} priority />
      </Link>
      <main className="mt-8 w-full max-w-md">{children}</main>
    </div>
  );
}
