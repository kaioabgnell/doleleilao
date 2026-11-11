"use client";

import { useEffect, useRef, useState } from "react";
import { RealtimeClient, type ConnectionState } from "./realtime";

/** Uma conexão por tela (vários canais por ela). `authEndpoint` assina os canais privados. */
export function useRealtimeClient(authEndpoint: string | null): { client: RealtimeClient | null; state: ConnectionState } {
  const [client, setClient] = useState<RealtimeClient | null>(null);
  const [state, setState] = useState<ConnectionState>("connecting");

  useEffect(() => {
    const instance = new RealtimeClient(authEndpoint);
    const off = instance.onState(setState);
    // Disponibiliza o cliente depois da montagem (o WebSocket só existe no navegador).
    queueMicrotask(() => setClient(instance));
    return () => {
      off();
      instance.close();
      setClient(null);
    };
  }, [authEndpoint]);

  return { client, state };
}

/**
 * Assina um canal enquanto o componente estiver na tela. `onSubscribed` roda a cada (re)assinatura:
 * a hora de buscar o estado atual (RF-F11).
 */
export function useChannel(
  client: RealtimeClient | null,
  channel: string | null,
  onEvent: (event: string, data: unknown) => void,
  onSubscribed?: () => void,
): void {
  const handlers = useRef({ onEvent, onSubscribed });
  useEffect(() => {
    handlers.current = { onEvent, onSubscribed };
  });

  useEffect(() => {
    if (!client || !channel) return;
    return client.subscribe(
      channel,
      (event, data) => handlers.current.onEvent(event, data),
      () => handlers.current.onSubscribed?.(),
    );
  }, [client, channel]);
}

/** Diferença entre o relógio do servidor e o do aparelho, medida numa requisição (descontando metade da ida e volta). */
export function measureOffset(serverNowIso: string, sentAt: number, receivedAt: number): number {
  return Date.parse(serverNowIso) + (receivedAt - sentAt) / 2 - receivedAt;
}
