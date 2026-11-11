<?php

return [
    /*
    | Endereço do site (Next.js). Usado nos links dos e-mails, nos redirecionamentos depois da
    | validação de e-mail e no link público da loja (doleleilao.com.br/{slug}).
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    /*
    | Catálogo de temas das lojas, gerado pelo pacote design-tokens.
    */
    /*
    | Segredo compartilhado com o site para descartar o cache das lojas (POST /api/revalidate).
    | Vazio = não avisa o site (testes).
    */
    'revalidate_secret' => env('REVALIDATE_SECRET', ''),

    'theme_catalog' => resource_path('design/theme-catalog.json'),

    /*
    | Primeiros passos (D-42, D-55). Os passos que ainda não existem aparecem como "em breve".
    */
    'onboarding' => [
        'available_steps' => ['company', 'products', 'payment', 'auction'],
    ],
];
