"use client";

import { useCallback, useLayoutEffect, useRef, useState } from "react";

/**
 * Formulários de cadastro ("novo produto", "novo lote") começam limpos de novo depois de criar.
 *
 * Com `cacheComponents`, o Next guarda a página anterior escondida em `<Activity>` em vez de
 * desmontar, e o formulário voltaria com os dados do último cadastro. Depois de criar, `markCreated()`
 * pede o reset; ele acontece quando a página é escondida (sem piscar o formulário vazio antes da
 * navegação). Rascunho não salvo continua guardado, como o Next prevê.
 *
 * Use a chave devolvida no `key` do formulário.
 */
export function useFreshAfterCreate(): [key: number, markCreated: () => void] {
  const [key, setKey] = useState(0);
  const pending = useRef(false);

  useLayoutEffect(
    () => () => {
      if (pending.current) {
        pending.current = false;
        setKey((k) => k + 1);
      }
    },
    [],
  );

  const markCreated = useCallback(() => {
    pending.current = true;
  }, []);

  return [key, markCreated];
}
