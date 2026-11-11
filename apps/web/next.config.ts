import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Pacotes do monorepo publicados como TypeScript fonte.
  transpilePackages: ["@doleleilao/design-tokens", "@doleleilao/realtime-contract"],
  cacheComponents: true,
  partialPrefetching: true,
  // Site institucional (Fase 7): página estática em public/index.html, feita com o kit da taste-skill.
  async rewrites() {
    return { beforeFiles: [{ source: "/", destination: "/index.html" }], afterFiles: [], fallback: [] };
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
