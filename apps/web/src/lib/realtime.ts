/**
 * Cliente mínimo do protocolo Pusher (o Reverb fala esse protocolo). Só recebe eventos: o lance vai
 * por HTTP (T-02). Reconecta sozinho com espera crescente e avisa quando (re)assina um canal, para a
 * tela buscar o estado de novo (RF-F11). Substitui pusher-js + laravel-echo (T-15).
 */

export type ConnectionState = "connecting" | "connected" | "offline";
type EventListener = (event: string, data: unknown) => void;

interface Subscription {
  listeners: Set<EventListener>;
  onSubscribed: Set<() => void>;
}

const config = {
  key: process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? "",
  host: process.env.NEXT_PUBLIC_REVERB_HOST ?? "localhost",
  port: process.env.NEXT_PUBLIC_REVERB_PORT ?? "8085",
  scheme: process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "http",
};

export class RealtimeClient {
  private socket: WebSocket | null = null;
  private socketId: string | null = null;
  private state: ConnectionState = "connecting";
  private readonly stateListeners = new Set<(state: ConnectionState) => void>();
  private readonly channels = new Map<string, Subscription>();
  private retries = 0;
  private retryTimer: ReturnType<typeof setTimeout> | null = null;
  private activityTimer: ReturnType<typeof setTimeout> | null = null;
  private pongTimer: ReturnType<typeof setTimeout> | null = null;
  private activityTimeoutMs = 30_000;
  private closed = false;

  constructor(private readonly authEndpoint: string | null = null) {
    if (typeof window !== "undefined") {
      window.addEventListener("online", this.reconnectNow);
      document.addEventListener("visibilitychange", this.onVisible);
    }
    this.connect();
  }

  get connectionState(): ConnectionState {
    return this.state;
  }

  onState(listener: (state: ConnectionState) => void): () => void {
    this.stateListeners.add(listener);
    return () => this.stateListeners.delete(listener);
  }

  /** Assina o canal. `onSubscribed` roda a cada (re)assinatura: é a hora de buscar o estado. */
  subscribe(channel: string, listener: EventListener, onSubscribed?: () => void): () => void {
    let sub = this.channels.get(channel);
    if (!sub) {
      sub = { listeners: new Set(), onSubscribed: new Set() };
      this.channels.set(channel, sub);
      if (this.state === "connected") void this.sendSubscribe(channel);
    }
    sub.listeners.add(listener);
    if (onSubscribed) sub.onSubscribed.add(onSubscribed);

    return () => {
      sub.listeners.delete(listener);
      if (onSubscribed) sub.onSubscribed.delete(onSubscribed);
      if (sub.listeners.size === 0) {
        this.channels.delete(channel);
        this.send("pusher:unsubscribe", { channel });
      }
    };
  }

  close(): void {
    this.closed = true;
    this.clearTimers();
    if (typeof window !== "undefined") {
      window.removeEventListener("online", this.reconnectNow);
      document.removeEventListener("visibilitychange", this.onVisible);
    }
    this.socket?.close();
  }

  private connect(): void {
    if (this.closed || !config.key) {
      this.setState("offline");
      return;
    }
    this.setState("connecting");
    const protocol = config.scheme === "https" ? "wss" : "ws";
    const socket = new WebSocket(`${protocol}://${config.host}:${config.port}/app/${config.key}?protocol=7&client=dl&version=1.0`);
    this.socket = socket;

    socket.onmessage = (message) => this.handle(String(message.data));
    socket.onclose = () => {
      if (this.socket !== socket) return;
      this.socket = null;
      this.socketId = null;
      this.clearTimers();
      this.setState("offline");
      this.scheduleReconnect();
    };
    socket.onerror = () => socket.close();
  }

  private handle(raw: string): void {
    let message: { event?: string; channel?: string; data?: unknown };
    try {
      message = JSON.parse(raw) as typeof message;
    } catch {
      return;
    }
    this.armActivityTimer();
    const data = typeof message.data === "string" ? safeJson(message.data) : message.data;

    switch (message.event) {
      case "pusher:connection_established": {
        const info = data as { socket_id: string; activity_timeout?: number };
        this.socketId = info.socket_id;
        this.activityTimeoutMs = Math.min((info.activity_timeout ?? 30) * 1000, 60_000);
        this.retries = 0;
        this.setState("connected");
        for (const channel of this.channels.keys()) void this.sendSubscribe(channel);
        return;
      }
      case "pusher:ping":
        this.send("pusher:pong", {});
        return;
      case "pusher:pong":
        if (this.pongTimer) clearTimeout(this.pongTimer);
        return;
      case "pusher_internal:subscription_succeeded": {
        const sub = message.channel ? this.channels.get(message.channel) : undefined;
        sub?.onSubscribed.forEach((fn) => fn());
        return;
      }
      case "pusher:error":
        return;
    }

    if (message.channel && message.event) {
      this.channels.get(message.channel)?.listeners.forEach((fn) => fn(message.event as string, data));
    }
  }

  private async sendSubscribe(channel: string): Promise<void> {
    if (!channel.startsWith("private-")) {
      this.send("pusher:subscribe", { channel });
      return;
    }
    if (!this.authEndpoint || !this.socketId) return;
    try {
      const response = await fetch(this.authEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ socket_id: this.socketId, channel_name: channel }),
      });
      if (!response.ok) return;
      const { auth } = (await response.json()) as { auth: string };
      this.send("pusher:subscribe", { channel, auth });
    } catch {
      // sem o canal privado, os avisos chegam pela próxima resposta HTTP
    }
  }

  private send(event: string, data: object): void {
    if (this.socket?.readyState === WebSocket.OPEN) this.socket.send(JSON.stringify({ event, data }));
  }

  /** Sem mensagens por um tempo, pergunta se a conexão está viva; sem resposta, reconecta. */
  private armActivityTimer(): void {
    if (this.activityTimer) clearTimeout(this.activityTimer);
    this.activityTimer = setTimeout(() => {
      this.send("pusher:ping", {});
      this.pongTimer = setTimeout(() => this.socket?.close(), 10_000);
    }, this.activityTimeoutMs);
  }

  private scheduleReconnect(): void {
    if (this.closed || this.retryTimer) return;
    const delay = Math.min(1000 * 2 ** this.retries, 10_000) + Math.random() * 500;
    this.retries++;
    this.retryTimer = setTimeout(() => {
      this.retryTimer = null;
      this.connect();
    }, delay);
  }

  private readonly reconnectNow = (): void => {
    if (this.socket || this.closed) return;
    if (this.retryTimer) clearTimeout(this.retryTimer);
    this.retryTimer = null;
    this.retries = 0;
    this.connect();
  };

  private readonly onVisible = (): void => {
    if (document.visibilityState === "visible") this.reconnectNow();
  };

  private clearTimers(): void {
    for (const timer of [this.activityTimer, this.pongTimer]) if (timer) clearTimeout(timer);
    this.activityTimer = null;
    this.pongTimer = null;
  }

  private setState(state: ConnectionState): void {
    if (this.state === state) return;
    this.state = state;
    this.stateListeners.forEach((fn) => fn(state));
  }
}

function safeJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}
