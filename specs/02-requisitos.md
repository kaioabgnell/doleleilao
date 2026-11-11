# 02 — Requisitos

IDs estáveis para rastrear no roadmap, nas issues e nos testes. **CA** = critério de aceite.

## Requisitos funcionais

### A. Cadastro e conta do leiloeiro

| ID | Requisito | CA principais |
|---|---|---|
| RF-A01 | Cadastro de leiloeiro: nome, e-mail, telefone, senha, tipo de documento (CPF ou CNPJ), documento, nome da empresa de leilão | O CPF/CNPJ é validado pelo dígito verificador e é único na plataforma. O e-mail também é único entre leiloeiros |
| RF-A02 | Escolha do **slug** da loja no cadastro | Aceita só `a-z`, `0-9` e `-`, com 3 a 40 caracteres. É único e não pode ser uma palavra reservada (`painel`, `api`, `login`, `cadastro`, `admin`, `app`, `static`, `_next`, `ajuda`, `termos`, `privacidade`, `blog`…). O sistema sugere um slug a partir do nome da empresa e confere a disponibilidade em tempo real |
| RF-A03 | **Nome de exibição** da loja | Vem preenchido com as 2 primeiras palavras do nome da empresa, ignorando conectivos (de, do, da, dos, das, e). É editável, com no máximo 2 palavras e 24 caracteres. É usado como logo de texto quando não houver logo (D-10) |
| RF-A04 | Validação de e-mail do leiloeiro | A loja só fica pública depois do e-mail validado |
| RF-A05 | Login, logout e recuperação de senha do leiloeiro | Link de redefinição válido por 60 min |
| RF-A06 | Troca de slug | Permitida 1 vez a cada 30 dias. O slug antigo redireciona com 301 por 90 dias. O novo slug passa pela **mesma verificação de unicidade**, que considera também os slugs antigos ainda em redirecionamento (nenhuma outra loja pode pegá-los nesse período) (D-29) |

### A2. Primeiros passos do leiloeiro (onboarding)

Depois do primeiro cadastro, o topo do dashboard mostra a seção **"Comece seu primeiro leilão"**, com 4 passos (D-42, D-55):

| ID | Requisito | CA principais |
|---|---|---|
| RF-A10 | Seção de **4 passos** no topo do dashboard: **1. Complete os dados da sua empresa**, **2. Cadastre produtos**, **3. Configure o pagamento**, **4. Cadastre o leilão** | Aparece desde o primeiro acesso ao painel. Mostra o progresso ("1 de 4"), o estado de cada passo (feito, próximo, pendente) e um botão que leva direto à tela do passo |
| RF-A11 | **Passo 1 concluído** quando: e-mail validado, telefone/WhatsApp de contato preenchido (RF-B08) e modelo da loja escolhido (RF-B01). Logo, cores e fonte são opcionais (têm padrão do modelo) | O botão abre a tela "Dados da empresa", que reúne contato e personalização num só lugar |
| RF-A12 | **Passo 2 concluído** quando existe **pelo menos 1 produto disponível** (com ao menos 1 foto, valor inicial e incremento) | O botão abre "Novo produto" |
| RF-A17 | **Passo 3 concluído** quando o leiloeiro tem **cartão de crédito cadastrado** em Configurações → Pagamento (D-54). É obrigatório: sem ele não dá para agendar leilão | O botão abre "Pagamento". O texto diz que é obrigatório para agendar e que cadastro e uso continuam grátis. Sem cartão, a seção aparece mesmo para quem já a tinha concluído ou ocultado (D-55) |
| RF-A13 | **Passo 4 concluído** quando existe **pelo menos 1 lote de leilão agendado** (com data/hora e ≥ 1 item) | O botão abre "Novo lote". Enquanto faltar produto disponível ou o cartão, o passo 4 diz o que falta ("Cadastre um produto e o cartão de crédito primeiro") |
| RF-A14 | Ordem sugerida, sem bloquear o painel | O próximo passo pendente fica destacado como ação principal. O resto do painel continua acessível |
| RF-A15 | O progresso é **calculado a partir dos dados reais**, não de cliques | Se o leiloeiro apagar o único produto, o passo 2 volta a pendente (até a seção ser concluída) |
| RF-A16 | Conclusão | Com os 4 passos feitos, a seção mostra "Tudo pronto! Seu primeiro leilão está agendado", com o link da loja (`doleleilao.com.br/{slug}`) para copiar e compartilhar, e o botão "Ocultar". Depois de ocultada, não volta, a não ser que falte o cartão (RF-A17). O evento `PrimeirosPassosConcluidos` é registrado (analytics) |

### B. Personalização da loja (painel)

| ID | Requisito | CA principais |
|---|---|---|
| RF-B01 | Escolher o modelo da loja: **Relógios**, **Roupa** ou **Eletrônicos** | A troca de modelo não perde conteúdo (produtos, banners e categorias são os mesmos) |
| RF-B02 | Enviar logo | SVG ou PNG de até 2 MB, com corte e enquadramento na hora do envio. O SVG é sanitizado (remove scripts) |
| RF-B03 | Escolher a cor **primária** e a cor de **destaque** numa lista fixa de 10 cores | Só cores da lista ([07-design-system.md](07-design-system.md#personalização-do-leiloeiro)). A pré-visualização é ao vivo |
| RF-B04 | Escolher o **fundo** entre 3 opções do modelo | O modelo Relógios inclui uma opção escura. O sistema bloqueia combinações que reprovam no contraste WCAG AA |
| RF-B05 | Escolher a **fonte** dos títulos: Inter, Roboto, Poppins, DM Sans ou Playfair Display | Vale para títulos, nomes de item e logo de texto. Textos e números ficam sempre em Inter |
| RF-B06 | Cadastro de **banners** (imagem desktop + imagem mobile, link, ordem, período de exibição) | Mobile first: a imagem mobile é obrigatória |
| RF-B07 | Cadastro de **categorias** (nome, slug, ordem, imagem opcional) | |
| RF-B08 | Contato do leiloeiro (WhatsApp, e-mail, redes sociais) e CEP para envio de produtos (opcional) | O contato é usado no "fale com o leiloeiro" (RF-E09). Ao completar o CEP, o endereço aparece preenchido para conferir; ele não é mostrado na loja (T-36) |
| RF-B09 | Pré-visualizar a loja antes de publicar as mudanças de personalização | |

### C. Catálogo de produtos (painel)

| ID | Requisito | CA principais |
|---|---|---|
| RF-C01 | Cadastro de produto: título, descrição, categoria, fotos (várias), link de vídeo do YouTube, **valor do produto** (referência), **valor inicial do lance**, **valor de incremento** | O **incremento é um campo livre em reais**, com máscara de moeda (ex.: R$ 100,00, R$ 200,00, R$ 500,00) e mínimo de R$ 1,00. O valor inicial precisa ser > 0 (D-24) |
| RF-C02 | Upload de imagens com geração automática de tamanhos (thumb, card, zoom) em WebP/AVIF | No máximo 10 imagens por produto, com 10 MB cada |
| RF-C03 | Vídeo do produto **somente por link do YouTube** | A plataforma armazena só fotos. Ao colar o link, o sistema valida e extrai o ID do vídeo. O player só carrega quando a pessoa toca (fachada leve) (D-26) |
| RF-C04 | **Contagem de visualizações** por produto | Conta uma visualização por pessoa (sessão ou usuário) a cada 30 min. O número aparece no painel |
| RF-C05 | Status do produto: rascunho, disponível, em leilão, arrematado, não arrematado | Um produto só entra em um lote ativo por vez |
| RF-C06 | **Ficha técnica livre**: o leiloeiro adiciona quantos pares nome/valor quiser (ex.: "Calibre: automático", "Tamanho: M", "Memória: 256 GB") | Não há campos fixos por modelo nem por ramo. O sistema sugere nomes já usados pela própria loja (autocompletar) (D-27) |

### D. Lotes de leilão (painel)

> **Lote de leilão** = o grupo de itens que serão leiloados naquele dia. Na loja pública ele aparece como "leilão" (D-22).

| ID | Requisito | CA principais |
|---|---|---|
| RF-D01 | Criar **lote de leilão**: nome, slug, descrição, capa, data e hora de início, itens (produtos selecionados do catálogo) e a ordem deles | A data de início fica no futuro. O valor inicial e o incremento vêm do produto e podem ser ajustados até o item abrir. Depois de iniciado, o lote só muda pelos controles do pregão |
| RF-D02 | Configurações do pregão: duração do item (ex.: 60 s), prorrogação a cada lance nos segundos finais (ex.: +15 s se houver lance nos últimos 15 s) e o intervalo entre itens | Valores padrão sugeridos e editáveis |
| RF-D03 | Configurações de chat: **quem pode ver** (todos ou só logados) e **quem pode escrever** (logados ou só com cadastro completo), chat ligado/desligado | Para escrever, a pessoa precisa estar logada (briefing) |
| RF-D04 | Habilitação **automática**: o participante com **cadastro completo** (nome, CPF, telefone, e-mail validado) fica habilitado em todos os lotes da loja | Não existe aprovação manual. O participante vê o que falta no cadastro e completa sem sair do auditório (D-23) |
| RF-D05 | Controles do pregão ao vivo: iniciar, pausar e retomar, encerrar o item agora, pular item, cancelar o último lance (com motivo registrado) | Toda ação fica num log de auditoria |
| RF-D06 | Status do lote: rascunho, agendado, ao vivo, encerrado, cancelado | |

### E. Loja pública e participante

| ID | Requisito | CA principais |
|---|---|---|
| RF-E01 | Página da loja `/{slug}`: banners, categorias, próximos leilões, leilão ao vivo em destaque, produtos | Renderização no servidor e Lighthouse mobile ≥ 90 em performance |
| RF-E02 | Página do leilão (lote) com a lista de itens e a página de cada item (galeria de fotos, vídeo do YouTube, ficha técnica, valores, visualizações) | |
| RF-E03 | Cadastro do participante **na loja do leiloeiro**: nome, **CPF (obrigatório)**, e-mail, telefone, senha e, **opcionalmente**, foto **ou avatar** | A conta vale só para aquele leiloeiro (D-11). O CPF é validado pelo dígito verificador e é único por loja, e fica criptografado (D-32). **A foto não é obrigatória** (D-38): o participante pode enviar uma foto (com corte, reduzida no servidor) ou escolher um avatar da galeria. Sem nenhum dos dois, aparecem as iniciais do nome |
| RF-E04 | Validação de e-mail do participante | Faz parte do cadastro completo (RF-D04) |
| RF-E05 | **Favoritar** produtos | Exige login. Se a pessoa tocar em favoritar sem estar logada, o pedido fica guardado e é aplicado depois do login |
| RF-E06 | **Alertas**: aviso por e-mail (v1) X minutos antes do início de um leilão em que o participante favoritou algum item ou que ele marcou "me avise" | Push no app (v2) |
| RF-E07 | "Minha conta": dados pessoais (com CEP de entrega opcional, T-36), foto ou avatar, situação da habilitação (completo ou o que falta), favoritos, próximos leilões, alertas, **arrematações** | |
| RF-E08 | Gestão de itens arrematados: lista com status (aguardando contato, em negociação, pago, entregue), mensagens do leiloeiro | Os status são atualizados pelo leiloeiro |
| RF-E10 | **"Quero participar"** na página do leilão (lote) | Exige login. Não é aprovação: o cadastro completo continua habilitando automaticamente (D-23, D-44). Marca o interesse do participante naquele leilão, que passa a receber os avisos de véspera e do dia (RF-N03). Com o cadastro incompleto, mostra o que falta e envia "Seu cadastro está habilitado…" assim que ele completar. Dá para desmarcar até o leilão começar |
| RF-E09 | "Quer leiloar algum produto seu? Fale com o leiloeiro": formulário curto (descrição, fotos opcionais, contato) que chega ao painel do leiloeiro e o avisa por e-mail | |

### F. Auditório virtual (`/{slug}/leilao/{leilao}`)

| ID | Requisito | CA principais |
|---|---|---|
| RF-F01 | Mostrar o item atual (mídia, título, lance atual, próximo lance mínimo, cronômetro, quem está ganhando por apelido) e a fila de próximos itens | O cronômetro segue o horário do servidor, e a diferença entre aparelhos é ≤ 500 ms |
| RF-F02 | **Dar lance** em um toque com o valor sugerido (lance atual + incremento) ou com um valor maior | Lance confirmado em até 300 ms (p95) para todos os conectados. Lance duplicado por clique duplo é ignorado (chave de idempotência) |
| RF-F03 | **Lance automático**: o participante define um valor máximo, e o sistema cobre outros lances de incremento em incremento até esse limite | Ver as regras em [03-dominio.md](03-dominio.md#regras-de-lance) |
| RF-F04 | Estados visuais fixos: ganhando (verde), superado (vermelho), AO VIVO (vermelho com ponto pulsante), encerrado (cinza) | As cores não mudam com a personalização (D-05) |
| RF-F05 | **Chat em tempo real** com todas as mensagens | Rolagem automática que pausa quando a pessoa sobe para ler. Mensagem nova chega em ≤ 500 ms |
| RF-F06 | **Responder** uma mensagem citando a original, como no WhatsApp | Tocar na citação rola até a original |
| RF-F07 | **Emojis** no chat (seletor de emojis) | Seletor carregado só quando é aberto (lazy) |
| RF-F08 | Moderação do chat pelo leiloeiro: apagar mensagem, silenciar participante no leilão, banir (RF-G02) | |
| RF-F09 | **Tela do ganhador**: animação de confetes + mensagem "Você é o ganhador!" na tela + **aviso sonoro curto ("plim")**, só no aparelho do vencedor | Sem locução (D-33). O som é liberado pelo toque em "Entrar no auditório" (regra de autoplay dos navegadores). Respeita "reduzir movimento" e tem botão de silenciar |
| RF-F10 | Quem não está logado assiste, mas não dá lance e não escreve no chat. A visibilidade do chat segue RF-D03 | |
| RF-F11 | Reconexão automática: ao perder a conexão, o auditório mostra o aviso "reconectando" e, ao voltar, recarrega o estado atual (item, lance, cronômetro) | Sem conexão, o selo "AO VIVO" dá lugar a "reconectando", aparece a hora da última atualização e o botão de lance fica desabilitado |

### G. Painel admin: usuários, analytics e comunicação

| ID | Requisito | CA principais |
|---|---|---|
| RF-G01 | Lista de participantes cadastrados (busca, filtros, data de cadastro, último acesso, nº de lances, nº de arremates) | |
| RF-G02 | **Banir** participante (com motivo) e desbanir | O efeito é imediato: a sessão é derrubada do auditório, e o participante não dá lance, não escreve no chat e não faz login nessa loja |
| RF-G03 | **Analytics em tempo real**: pessoas online agora na loja e em cada auditório (logadas e visitantes), atualizado a cada ≤ 5 s | |
| RF-G04 | Analytics histórico simples: visitas, visualizações por produto, lances por leilão, taxa de arremate | |
| RF-G05 | **Enviar mensagem ao vencedor** (modelo de mensagem pronto e editável) | A mensagem chega por e-mail e na "Minha conta" do participante. Um botão **"Abrir no WhatsApp"** abre `https://wa.me/55{telefone}?text={mensagem}` com o texto pronto, sem API paga (D-25) |
| RF-G06 | Lista de arrematações por lote, com exportação CSV | |
| RF-G07 | Caixa de entrada do "fale com o leiloeiro" (RF-E09) | |

### N. Notificações no site (sino)

Sino de notificações no topo do **painel do leiloeiro** e no topo da **loja** (participante logado), D-43. As notificações ficam guardadas na plataforma (in-app). O e-mail continua para o que já está previsto (validação, alertas, mensagem ao vencedor).

| ID | Requisito | CA principais |
|---|---|---|
| RF-N01 | Sino no topo do painel e da loja, com o número de não lidas | O número mostra até "9+". Sem notificação, o sino aparece sem número. Área de toque ≥ 44 px |
| RF-N02 | Lista das notificações ao tocar no sino | Mais recentes primeiro, 20 por vez ("ver mais"). Cada uma tem título, texto, quando chegou ("há 5 min") e leva ao destino. Tocar marca como lida. Botão "Marcar todas como lidas" |
| RF-N03 | **Notificações do participante** | (1) **"O leilão {nome} é amanhã, às {hora}"** e (2) **"O leilão {nome} é hoje, às {hora}"**, para quem tocou em "Quero participar" (RF-E10). (3) **"Você foi o vencedor no leilão {nome} em {data}"**, com o item e o valor. (4) **"Seu cadastro está habilitado para participar do leilão {nome}"**, quando toca em "Quero participar" com o cadastro completo (ou assim que completar o cadastro) |
| RF-N04 | **Notificações do leiloeiro** | "Boas-vindas ao Dole Leilão" (no cadastro, com os 3 primeiros passos), "Sua loja está no ar" (primeira vez que fica pública), "Seu leilão {nome} é amanhã / hoje", "{item} foi arrematado por {valor}", "Novo participante no leilão {nome}" |
| RF-N05 | Horário dos avisos de véspera e do dia | Um processo agendado roda às 8h (horário de Brasília): avisa "amanhã" para os leilões do dia seguinte e "hoje" para os do dia. Quem toca em "Quero participar" depois disso, num leilão do mesmo dia, recebe o "hoje" na hora. Nenhum aviso é enviado em dobro |
| RF-N06 | Atualização do sino | O número é atualizado ao navegar e a cada 60 s (na v1, sem precisar recarregar a página) |
| RF-N07 | Guarda | As notificações ficam 90 dias. Cada uma pertence a uma única pessoa (isolamento por loja e por conta) |

### H. Site institucional Dole Leilão

| ID | Requisito | CA principais |
|---|---|---|
| RF-H01 | Home com a proposta de valor, os 3 modelos de loja, como funciona e a chamada para cadastro | Construído com as skills impeccable, emilkowalski e taste (D-14) |
| RF-H02 | Páginas: termos de uso, política de privacidade (LGPD), contato/ajuda | |
| RF-H03 | Rodapé das lojas: "desenvolvido por Doleleilao.com.br" com link `?utm_source={slug}&utm_medium=footer` | D-06, D-20 |

## Requisitos não funcionais

| ID | Requisito | Meta |
|---|---|---|
| RNF-01 | **Mobile first** | Todo layout é desenhado primeiro para 360 px. Áreas de toque ≥ 44 px |
| RNF-02 | **Performance web** | LCP < 2,5 s em 4G, INP < 200 ms, CLS < 0,1 (Core Web Vitals) nas páginas públicas |
| RNF-03 | **Latência de tempo real** | Lance e chat: p95 < 300 ms do servidor até os clientes |
| RNF-04 | **API** | p95 < 200 ms nas leituras e < 300 ms ao registrar um lance |
| RNF-05 | **Escala-alvo inicial (pequenos e médios)** | **5 lotes ao vivo ao mesmo tempo, com até 500 pessoas por auditório** (≈ 2.500 conexões WebSocket). Pico de **20 lances/s por item** (D-37). A arquitetura permite crescer depois sem reescrever (D-21) |
| RNF-06 | **Disponibilidade** | 99% mensal na v1 (servidor único, D-31). Uma queda no auditório pausa o pregão automaticamente (RNF-07). Backup diário do banco com retenção de 14 dias e restauração testada |
| RNF-07 | **Integridade do leilão** | O horário de encerramento é decidido só no servidor. Os lances são gravados em sequência por item (sem dois "vencedores"). O log de lances não pode ser alterado |
| RNF-08 | **Segurança** | OWASP ASVS nível 2, limite de requisições por usuário e IP em lances, chat e login, senhas com Argon2id e HTTPS obrigatório |
| RNF-09 | **LGPD** | Consentimento no cadastro, exportação e exclusão de dados pelo titular, CPF/CNPJ (leiloeiros e participantes) criptografado no banco e logs sem dados pessoais |
| RNF-10 | **Acessibilidade** | WCAG 2.2 AA (contraste, foco visível, leitores de tela no lance e no cronômetro, `prefers-reduced-motion`) |
| RNF-11 | **Preparado para app** | Toda funcionalidade fica exposta na API versionada (`/api/v1`). O contrato OpenAPI é a fonte da verdade, e a autenticação por token serve para o app |
| RNF-12 | **Observabilidade** | Logs estruturados, rastreamento de erros (Sentry), métricas de conexões WebSocket e de latência de lance |
| RNF-13 | **Idioma e moeda** | pt-BR, BRL. Valores guardados em centavos (inteiro). Horários guardados em UTC e exibidos em America/Sao_Paulo |
