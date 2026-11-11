"use client";

import { Check, ChevronDown, Loader2, Plus, X } from "lucide-react";
import { useId, useMemo, useState, type KeyboardEvent } from "react";
import { inputClass } from "@/components/ui/field";
import type { StoreCategory } from "@/lib/api-types";
import { panelRequest } from "@/lib/panel-client";

/** Comparação sem acento e sem diferença de maiúsculas ("relogios" acha "Relógios"). */
function normalize(text: string): string {
  return text.normalize("NFD").replace(/\p{Diacritic}/gu, "").trim().toLowerCase();
}

type Option = { kind: "category"; category: StoreCategory } | { kind: "create"; name: string };

interface Props {
  id: string;
  /** Nome do campo escondido com o id da categoria (vazio = sem categoria). */
  name: string;
  categories: StoreCategory[];
  defaultValue: string | null;
  error?: string;
  describedBy?: string;
  /** Avisa a página para recarregar a lista do servidor depois de criar. */
  onCreated?: (category: StoreCategory) => void;
}

/**
 * Categoria do produto: campo com autocompletar. Se o nome digitado não existe, a última opção
 * é "Criar “nome”", que cadastra a categoria na hora e já a seleciona.
 */
export function CategoryCombobox({ id, name, categories, defaultValue, error, describedBy, onCreated }: Props) {
  const listId = useId();
  const [created, setCreated] = useState<StoreCategory[]>([]);
  const list = useMemo(() => [...categories, ...created.filter((c) => !categories.some((k) => k.id === c.id))], [categories, created]);

  const [selectedId, setSelectedId] = useState<string | null>(defaultValue);
  const selected = list.find((c) => c.id === selectedId) ?? null;
  const [query, setQuery] = useState(selected?.name ?? "");
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);

  // Enquanto o texto é o da categoria escolhida, a lista mostra todas; ao digitar, filtra.
  const typing = query !== (selected?.name ?? "");
  const needle = normalize(query);
  const options: Option[] = useMemo(() => {
    const matches = typing && needle ? list.filter((c) => normalize(c.name).includes(needle)) : list;
    const exact = list.some((c) => normalize(c.name) === needle);
    const createName = query.trim().replace(/\s+/g, " ");
    return [
      ...matches.map((category) => ({ kind: "category" as const, category })),
      ...(typing && createName.length >= 2 && !exact ? [{ kind: "create" as const, name: createName.slice(0, 40) }] : []),
    ];
  }, [list, needle, query, typing]);

  function choose(category: StoreCategory | null) {
    setSelectedId(category?.id ?? null);
    setQuery(category?.name ?? "");
    setOpen(false);
    setCreateError(null);
  }

  async function create(newName: string) {
    setCreating(true);
    setCreateError(null);
    const form = new FormData();
    form.append("name", newName);
    const result = await panelRequest<StoreCategory>("categories", { method: "POST", body: form });
    setCreating(false);
    if (!result.ok || !result.data) {
      setCreateError(result.errors?.name?.[0] ?? result.message ?? "Não foi possível criar a categoria.");
      return;
    }
    const category = result.data;
    setCreated((current) => [...current, category]);
    choose(category);
    onCreated?.(category);
  }

  function pick(option: Option | undefined) {
    if (!option) return;
    if (option.kind === "category") choose(option.category);
    else void create(option.name);
  }

  /** Ao sair do campo: texto vazio tira a categoria; nome exato seleciona; o resto volta ao escolhido. */
  function commit() {
    setOpen(false);
    if (creating) return;
    if (!query.trim()) return choose(null);
    const exact = list.find((c) => normalize(c.name) === needle);
    if (exact) return choose(exact);
    setQuery(selected?.name ?? "");
  }

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      if (!open) {
        setOpen(true);
        setActive(0);
        return;
      }
      const delta = e.key === "ArrowDown" ? 1 : -1;
      setActive((i) => (options.length ? (i + delta + options.length) % options.length : 0));
    } else if (e.key === "Enter") {
      // Não envia o formulário enquanto a lista está aberta.
      if (open && options.length > 0) {
        e.preventDefault();
        pick(options[Math.min(active, options.length - 1)]);
      }
    } else if (e.key === "Escape" && open) {
      e.preventDefault();
      setOpen(false);
      setQuery(selected?.name ?? "");
    }
  }

  const activeId = open && options[active] ? `${listId}-${active}` : undefined;
  const message = createError ?? error;

  return (
    <div className="relative">
      <input type="hidden" name={name} value={selectedId ?? ""} />
      <input
        id={id}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={open}
        aria-controls={listId}
        aria-activedescendant={activeId}
        aria-invalid={message ? true : undefined}
        aria-describedby={describedBy}
        autoComplete="off"
        value={query}
        maxLength={40}
        placeholder="Sem categoria. Digite para buscar ou criar"
        onChange={(e) => {
          setQuery(e.target.value);
          setOpen(true);
          setActive(0);
          setCreateError(null);
        }}
        onFocus={() => setOpen(true)}
        onClick={() => setOpen(true)}
        onBlur={commit}
        onKeyDown={onKeyDown}
        className={`${inputClass} pr-20`}
      />
      <span className="absolute inset-y-0 right-1 flex items-center gap-0.5">
        {creating ? <Loader2 aria-hidden className="mx-2 size-4 animate-spin text-dl-ink-muted" /> : null}
        {selected && !creating ? (
          <button
            type="button"
            aria-label="Tirar a categoria"
            title="Tirar a categoria"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => choose(null)}
            className="flex size-9 items-center justify-center rounded-field text-dl-ink-muted hover:bg-dl-page hover:text-dl-ink"
          >
            <X aria-hidden className="size-4" />
          </button>
        ) : null}
        <ChevronDown aria-hidden className="pointer-events-none mr-2 size-4 text-dl-ink-muted" />
      </span>

      {open ? (
        <ul id={listId} role="listbox" aria-label="Categorias" className="absolute inset-x-0 top-full z-30 mt-1 max-h-64 overflow-y-auto rounded-field border border-dl-line bg-dl-card py-1 shadow-lg">
          {options.length === 0 ? (
            <li className="px-3 py-2.5 text-sm text-dl-ink-muted">{list.length === 0 ? "Nenhuma categoria ainda. Digite um nome para criar." : "Digite pelo menos 2 letras para criar."}</li>
          ) : (
            options.map((option, index) => {
              const isActive = index === active;
              const key = option.kind === "category" ? option.category.id : "create";
              const isSelected = option.kind === "category" && option.category.id === selectedId;
              return (
                <li
                  key={key}
                  id={`${listId}-${index}`}
                  role="option"
                  aria-selected={isSelected}
                  onMouseDown={(e) => e.preventDefault()}
                  onMouseEnter={() => setActive(index)}
                  onClick={() => pick(option)}
                  className={`flex min-h-11 cursor-pointer items-center gap-2 px-3 text-sm ${isActive ? "bg-dl-page" : ""} ${option.kind === "create" ? "border-t border-dl-line font-semibold text-dl-copper" : "text-dl-ink"}`}
                >
                  {option.kind === "create" ? (
                    <>
                      <Plus aria-hidden className="size-4 shrink-0" />
                      <span className="truncate">Criar “{option.name}”</span>
                    </>
                  ) : (
                    <>
                      <span className="flex-1 truncate">{option.category.name}</span>
                      {isSelected ? <Check aria-hidden className="size-4 shrink-0 text-dl-copper" /> : null}
                    </>
                  )}
                </li>
              );
            })
          )}
        </ul>
      ) : null}

      {createError ? (
        <p role="alert" className="mt-1.5 text-sm font-medium text-state-outbid">
          {createError}
        </p>
      ) : null}
    </div>
  );
}
