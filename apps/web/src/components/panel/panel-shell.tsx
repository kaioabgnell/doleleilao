"use client";

import Image from "next/image";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Suspense, useEffect, useId, useRef, useState, type ReactNode } from "react";
import { SIDEBAR_STORAGE_KEY } from "@/lib/panel-menu";
import {
  Building2,
  ChevronDown,
  ChevronRight,
  CreditCard,
  GalleryHorizontal,
  Gavel,
  Inbox,
  LayoutDashboard,
  type LucideIcon,
  Menu,
  Package,
  PanelLeftClose,
  PanelLeftOpen,
  Settings,
  Tags,
  Trophy,
  X,
} from "lucide-react";

interface NavItem {
  href: string | null;
  label: string;
  icon: LucideIcon;
}

/** Item com submenu: flutuante no menu lateral, expansível no celular. */
interface NavGroup {
  label: string;
  icon: LucideIcon;
  /** Caminho comum das páginas do grupo (destaca o item quando uma delas está aberta). */
  base: string;
  children: { href: string; label: string; icon: LucideIcon }[];
}

type NavEntry = NavItem | NavGroup;

const items: NavEntry[] = [
  { href: "/painel", label: "Início", icon: LayoutDashboard },
  { href: "/painel/dados-da-empresa", label: "Dados da empresa", icon: Building2 },
  { href: "/painel/banners", label: "Banners", icon: GalleryHorizontal },
  { href: "/painel/categorias", label: "Categorias", icon: Tags },
  { href: "/painel/produtos", label: "Produtos", icon: Package },
  { href: "/painel/pedidos", label: "Pedidos para leiloar", icon: Inbox },
  { href: "/painel/leiloes", label: "Leilões", icon: Gavel },
  { href: "/painel/arrematacoes", label: "Arrematações", icon: Trophy },
  {
    label: "Configurações",
    icon: Settings,
    base: "/painel/configuracoes",
    children: [{ href: "/painel/configuracoes/pagamento", label: "Pagamento", icon: CreditCard }],
  },
];

const isGroup = (entry: NavEntry): entry is NavGroup => "children" in entry;

const linkClass = (active: boolean) =>
  `flex min-h-11 items-center gap-3 rounded-field px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-dl-copper ${
    active ? "bg-dl-page text-dl-copper" : "text-dl-ink-muted hover:bg-dl-page hover:text-dl-ink"
  }`;


/**
 * Estrutura do painel (D-45): menu lateral recolhível no computador (só ícones quando recolhido,
 * escolha guardada no navegador) e menu que abre por cima do conteúdo no celular.
 * O estado recolhido vem de `<html data-sidebar>`, aplicado antes da pintura (lib/panel-menu.ts).
 */
export function PanelShell({ topbar, children }: { topbar: ReactNode; children: ReactNode }) {
  const [drawerOpen, setDrawerOpen] = useState(false);

  useEffect(() => {
    if (!drawerOpen) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setDrawerOpen(false);
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [drawerOpen]);

  function toggleCollapsed() {
    const root = document.documentElement;
    const next = root.dataset.sidebar === "collapsed" ? "expanded" : "collapsed";
    root.dataset.sidebar = next;
    try {
      localStorage.setItem(SIDEBAR_STORAGE_KEY, next);
    } catch {
      // navegador sem armazenamento: vale só nesta visita
    }
  }

  return (
    <>
      {/* Computador: menu lateral fixo */}
      <aside
        aria-label="Menu do painel"
        className="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col border-r border-dl-line bg-dl-card transition-[width] duration-200 lg:flex lg:sidebar-collapsed:w-[4.5rem]"
      >
        <div className="flex h-16 items-center px-4 lg:sidebar-collapsed:justify-center lg:sidebar-collapsed:px-0">
          <Link href="/painel" aria-label="Início do painel" className="rounded-field">
            <Image
              src="/brand/logo-horizontal.svg"
              alt="Dole Leilão"
              width={118}
              height={45}
              priority
              className="lg:sidebar-collapsed:hidden"
            />
            <Image
              src="/brand/simbolo.svg"
              alt="Dole Leilão"
              width={36}
              height={36}
              className="hidden lg:sidebar-collapsed:block"
            />
          </Link>
        </div>
        <Nav />
        <div className="border-t border-dl-line p-3">
          <button
            type="button"
            onClick={toggleCollapsed}
            className="flex min-h-11 w-full items-center gap-3 rounded-field px-3 text-sm font-medium text-dl-ink-muted hover:bg-dl-page hover:text-dl-ink focus-visible:outline-2 focus-visible:outline-dl-copper lg:sidebar-collapsed:justify-center lg:sidebar-collapsed:px-0"
          >
            <PanelLeftClose aria-hidden className="size-5 shrink-0 lg:sidebar-collapsed:hidden" />
            <PanelLeftOpen aria-hidden className="hidden size-5 shrink-0 lg:sidebar-collapsed:block" />
            <span className="lg:sidebar-collapsed:sr-only">
              <span className="lg:sidebar-collapsed:hidden">Recolher menu</span>
              <span className="hidden lg:sidebar-collapsed:inline">Expandir menu</span>
            </span>
          </button>
        </div>
      </aside>

      {/* Celular: menu por cima do conteúdo */}
      {drawerOpen ? (
        <div className="fixed inset-0 z-40 lg:hidden">
          <button
            type="button"
            aria-label="Fechar menu"
            onClick={() => setDrawerOpen(false)}
            className="absolute inset-0 bg-dl-ink/40"
          />
          <aside
            id="panel-drawer"
            aria-label="Menu do painel"
            className="absolute inset-y-0 left-0 flex w-[min(18rem,85vw)] flex-col bg-dl-card shadow-xl"
          >
            <div className="flex h-16 items-center justify-between px-4">
              <Image src="/brand/logo-horizontal.svg" alt="Dole Leilão" width={118} height={45} />
              <button
                type="button"
                onClick={() => setDrawerOpen(false)}
                aria-label="Fechar menu"
                className="flex size-11 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page focus-visible:outline-2 focus-visible:outline-dl-copper"
              >
                <X aria-hidden className="size-5" />
              </button>
            </div>
            <Nav mobile onNavigate={() => setDrawerOpen(false)} />
          </aside>
        </div>
      ) : null}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-16 items-center gap-2 bg-dl-topbar px-2 text-dl-on-topbar sm:px-4">
          <button
            type="button"
            onClick={() => setDrawerOpen(true)}
            aria-label="Abrir menu"
            aria-expanded={drawerOpen}
            aria-controls="panel-drawer"
            className="flex size-11 items-center justify-center rounded-field text-dl-on-topbar hover:bg-dl-topbar-hover focus-visible:outline-2 focus-visible:outline-dl-on-topbar lg:hidden"
          >
            <Menu aria-hidden className="size-6" />
          </button>
          <Link href="/painel" aria-label="Início do painel" className="rounded-field focus-visible:outline-2 focus-visible:outline-dl-on-topbar lg:hidden">
            <Image src="/brand/simbolo.svg" alt="" width={32} height={32} priority />
          </Link>
          <div className="ml-auto flex min-w-0 items-center gap-1 sm:gap-2">{topbar}</div>
        </header>
        <main className="mx-auto w-full max-w-panel flex-1 px-4 py-6 sm:px-6 sm:py-8">{children}</main>
      </div>
    </>
  );
}

/** O destaque do item atual lê a URL; fica num Suspense para não travar o pré-render das rotas dinâmicas. */
function Nav({ mobile = false, onNavigate }: { mobile?: boolean; onNavigate?: () => void }) {
  return (
    <Suspense fallback={<NavList pathname="" mobile={mobile} onNavigate={onNavigate} />}>
      <ActiveNav mobile={mobile} onNavigate={onNavigate} />
    </Suspense>
  );
}

function ActiveNav(props: { mobile?: boolean; onNavigate?: () => void }) {
  return <NavList pathname={usePathname()} {...props} />;
}

function NavList({ pathname, mobile = false, onNavigate }: { pathname: string; mobile?: boolean; onNavigate?: () => void }) {
  const collapsedLabel = mobile ? "" : "lg:sidebar-collapsed:sr-only";
  const collapsedItem = mobile ? "" : "lg:sidebar-collapsed:justify-center lg:sidebar-collapsed:px-0";

  return (
    <nav aria-label="Painel" className="flex-1 overflow-y-auto p-3">
      <ul className="flex flex-col gap-1">
        {items.map((entry) => {
          if (isGroup(entry)) {
            return mobile ? (
              <NavDisclosure key={entry.label} group={entry} pathname={pathname} onNavigate={onNavigate} />
            ) : (
              <NavFlyout key={entry.label} group={entry} pathname={pathname} />
            );
          }
          const { href, label, icon: Icon } = entry;
          if (!href) {
            return (
              <li key={label}>
                <span
                  aria-disabled="true"
                  title={`${label} (em breve)`}
                  className={`flex min-h-11 items-center gap-3 rounded-field px-3 text-sm text-dl-ink-muted/70 ${collapsedItem}`}
                >
                  <Icon aria-hidden className="size-5 shrink-0" />
                  <span className={`flex flex-1 items-center justify-between gap-2 ${collapsedLabel}`}>
                    {label}
                    <span className="rounded-full bg-dl-page px-2 py-0.5 text-xs">em breve</span>
                  </span>
                </span>
              </li>
            );
          }
          const active = href === "/painel" ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
          return (
            <li key={href}>
              <Link
                href={href}
                title={label}
                onClick={onNavigate}
                aria-current={active ? "page" : undefined}
                className={`${linkClass(active)} ${collapsedItem}`}
              >
                <Icon aria-hidden className="size-5 shrink-0" />
                <span className={collapsedLabel}>{label}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

const OPEN_DELAY_MS = 60;
const CLOSE_DELAY_MS = 180;

/**
 * Item com submenu no menu lateral (computador): o submenu flutua ao lado do menu ao passar o mouse,
 * com uma pequena espera para não piscar quando o mouse só atravessa o item. Clique, Enter e seta
 * para a direita também abrem; Esc, clique fora ou o foco saindo fecham. Fica por cima da página
 * (`fixed`), porque a lista do menu corta o que passa da borda.
 */
function NavFlyout({ group, pathname }: { group: NavGroup; pathname: string }) {
  const [place, setPlace] = useState<{ top: number; left: number } | null>(null);
  const rootRef = useRef<HTMLLIElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const timer = useRef<number | undefined>(undefined);
  // Aberto pelo mouse fecha ao sair; aberto por clique ou teclado fica até clicar fora ou Esc.
  const openedBy = useRef<"hover" | "click">("hover");
  // Seta para a direita: o foco vai para a primeira página assim que o submenu aparece.
  const focusFirst = useRef(false);
  const panelId = useId();
  const open = place !== null;
  const active = pathname === group.base || pathname.startsWith(`${group.base}/`);
  const Icon = group.icon;

  function show(by: "hover" | "click", delay = 0) {
    window.clearTimeout(timer.current);
    timer.current = window.setTimeout(() => {
      const trigger = triggerRef.current?.getBoundingClientRect();
      const menu = triggerRef.current?.closest("aside")?.getBoundingClientRect();
      if (!trigger) return;
      openedBy.current = by;
      setPlace({ top: Math.max(8, Math.min(trigger.top - 6, window.innerHeight - 160)), left: (menu?.right ?? trigger.right) + 8 });
    }, delay);
  }

  function hide(delay = 0) {
    window.clearTimeout(timer.current);
    timer.current = window.setTimeout(() => setPlace(null), delay);
  }

  useEffect(() => () => window.clearTimeout(timer.current), []);

  useEffect(() => {
    if (!place || !focusFirst.current) return;
    focusFirst.current = false;
    panelRef.current?.querySelector("a")?.focus();
  }, [place]);

  useEffect(() => {
    if (!open) return;
    const outside = (e: PointerEvent) => {
      if (!rootRef.current?.contains(e.target as Node)) hide();
    };
    // Rolar a lista ou a página deixa a posição velha: fecha.
    const moved = (e: Event) => {
      if (!panelRef.current?.contains(e.target as Node)) hide();
    };
    document.addEventListener("pointerdown", outside);
    window.addEventListener("scroll", moved, true);
    window.addEventListener("resize", moved);
    return () => {
      document.removeEventListener("pointerdown", outside);
      window.removeEventListener("scroll", moved, true);
      window.removeEventListener("resize", moved);
    };
  }, [open]);

  return (
    <li
      ref={rootRef}
      onPointerEnter={(e) => {
        if (e.pointerType === "mouse" && (!open || openedBy.current === "hover")) show("hover", open ? 0 : OPEN_DELAY_MS);
      }}
      onPointerLeave={(e) => {
        if (e.pointerType === "mouse" && openedBy.current === "hover") hide(open ? CLOSE_DELAY_MS : 0);
      }}
      onKeyDown={(e) => {
        if (!open) return;
        if (e.key === "Escape" || (e.key === "ArrowLeft" && panelRef.current?.contains(e.target as Node))) {
          e.preventDefault();
          e.stopPropagation();
          hide();
          triggerRef.current?.focus();
        }
      }}
      onBlur={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) hide();
      }}
    >
      <button
        ref={triggerRef}
        type="button"
        title={group.label}
        aria-expanded={open}
        aria-controls={open ? panelId : undefined}
        onClick={() => {
          if (!open) show("click");
          else if (openedBy.current === "hover") openedBy.current = "click"; // clicou enquanto o mouse abria: fixa aberto
          else hide();
        }}
        onKeyDown={(e) => {
          if (e.key !== "ArrowRight") return;
          e.preventDefault();
          if (open) {
            panelRef.current?.querySelector("a")?.focus();
            return;
          }
          focusFirst.current = true;
          show("click");
        }}
        className={`${linkClass(active || open)} w-full lg:sidebar-collapsed:justify-center lg:sidebar-collapsed:px-0`}
      >
        <Icon aria-hidden className="size-5 shrink-0" />
        <span className="flex-1 text-left lg:sidebar-collapsed:sr-only">{group.label}</span>
        <ChevronRight aria-hidden className={`size-4 shrink-0 transition-transform duration-150 lg:sidebar-collapsed:hidden ${open ? "translate-x-0.5" : ""}`} />
      </button>
      {place ? (
        <div
          ref={panelRef}
          id={panelId}
          style={{ top: place.top, left: place.left }}
          className="fixed z-50 w-56 animate-flyout-in rounded-card border border-dl-line bg-dl-card p-1.5 shadow-lg before:absolute before:inset-y-0 before:-left-5 before:w-5 before:content-['']"
        >
          <p className="px-3 pt-1.5 pb-1 text-xs font-semibold tracking-wide text-dl-ink-muted uppercase">{group.label}</p>
          <ul className="flex flex-col gap-0.5">
            {group.children.map(({ href, label, icon: ChildIcon }) => {
              const current = pathname === href || pathname.startsWith(`${href}/`);
              return (
                <li key={href}>
                  <Link href={href} aria-current={current ? "page" : undefined} onClick={() => hide()} className={linkClass(current)}>
                    <ChildIcon aria-hidden className="size-5 shrink-0" />
                    {label}
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}
    </li>
  );
}

/** Item com submenu no celular: sem mouse, abre e fecha por toque, com as páginas logo abaixo. */
function NavDisclosure({ group, pathname, onNavigate }: { group: NavGroup; pathname: string; onNavigate?: () => void }) {
  const active = pathname === group.base || pathname.startsWith(`${group.base}/`);
  const [open, setOpen] = useState(active);
  const listId = useId();
  const Icon = group.icon;

  return (
    <li>
      <button type="button" aria-expanded={open} aria-controls={listId} onClick={() => setOpen((v) => !v)} className={`${linkClass(active)} w-full`}>
        <Icon aria-hidden className="size-5 shrink-0" />
        <span className="flex-1 text-left">{group.label}</span>
        <ChevronDown aria-hidden className={`size-4 shrink-0 transition-transform duration-150 ${open ? "rotate-180" : ""}`} />
      </button>
      <ul id={listId} hidden={!open} className="mt-1 flex flex-col gap-1 pl-5">
        {group.children.map(({ href, label, icon: ChildIcon }) => {
          const current = pathname === href || pathname.startsWith(`${href}/`);
          return (
            <li key={href}>
              <Link href={href} onClick={onNavigate} aria-current={current ? "page" : undefined} className={linkClass(current)}>
                <ChildIcon aria-hidden className="size-5 shrink-0" />
                {label}
              </Link>
            </li>
          );
        })}
      </ul>
    </li>
  );
}
