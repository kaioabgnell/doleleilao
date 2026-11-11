"use client";

/**
 * Seletor de emojis do chat (RF-F07). Carregado só quando é aberto (React.lazy). Lista própria e
 * curta, sem biblioteca: os emojis mais usados em conversa e alguns do mundo dos leilões.
 */
const GROUPS: [string, string[]][] = [
  ["Leilão", ["🔨", "💰", "💸", "🏆", "🥇", "🎉", "🔥", "⏰", "⌛", "📈", "💎", "⌚", "👜", "👗", "👟", "📱", "💻", "🎧", "📷", "🚗"]],
  ["Rostos", ["😀", "😃", "😄", "😁", "😆", "😅", "😂", "🤣", "😊", "😉", "😍", "🤩", "😘", "😎", "🤔", "🤨", "😐", "🙄", "😏", "😬", "😮", "😯", "😲", "😳", "🥺", "😢", "😭", "😤", "😡", "🤯", "😱", "🥳", "🤑", "🤞", "😇", "🙃"]],
  ["Gestos", ["👍", "👎", "👏", "🙌", "🙏", "👋", "🤝", "💪", "👌", "✌️", "🤙", "👀", "☝️", "👇", "👉", "👈", "✋", "🫶"]],
  ["Símbolos", ["❤️", "🧡", "💛", "💚", "💙", "💜", "🖤", "💯", "✅", "❌", "❗", "❓", "⭐", "✨", "⚡", "💥", "🎯", "🚀"]],
];

export default function EmojiPicker({ onPick, tone }: { onPick: (emoji: string) => void; tone: "store" | "panel" }) {
  const box = tone === "store" ? "border-store-border bg-store-surface text-store-text" : "border-dl-line bg-dl-card text-dl-ink";
  const muted = tone === "store" ? "text-store-text-muted" : "text-dl-ink-muted";
  return (
    <div role="dialog" aria-label="Emojis" className={`max-h-64 w-full overflow-y-auto rounded-card border p-2 shadow-lg ${box}`}>
      {GROUPS.map(([name, emojis]) => (
        <div key={name} className="mb-1">
          <p className={`px-1 py-1 text-xs font-semibold ${muted}`}>{name}</p>
          <div className="grid grid-cols-8 gap-0.5 sm:grid-cols-10">
            {emojis.map((emoji) => (
              <button
                key={emoji}
                type="button"
                className="flex size-10 items-center justify-center rounded-field text-xl hover:bg-black/5 focus-visible:outline-2"
                onClick={() => onPick(emoji)}
                aria-label={emoji}
              >
                {emoji}
              </button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
