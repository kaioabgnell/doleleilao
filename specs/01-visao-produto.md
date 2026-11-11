# 01 — Visão do produto

## Problema

Pequenos e médios leiloeiros fazem leilões por grupos de WhatsApp e lives em redes sociais. O processo é informal: os lances se perdem nas mensagens, não existe registro auditável, o cronômetro é "de cabeça" e a apresentação é amadora. O **Dole Leilão** dá a esses leiloeiros uma loja própria, com a marca deles, e um auditório virtual em que os lances, o cronômetro e o chat acontecem em tempo real.

## Perfis de usuário

| Perfil | Quem é | Onde atua |
|---|---|---|
| **Leiloeiro (Admin)** | Pessoa ou empresa (CPF ou CNPJ) que se cadastra no Dole Leilão para leiloar seus itens | Painel admin (`doleleilao.com.br/painel`) |
| **Participante** | Pessoa que entra pela loja de um leiloeiro, se cadastra e dá lances | Loja pública, auditório e "Minha conta" dentro do slug do leiloeiro |
| **Visitante** | Pessoa sem login na loja | Vê a loja, os leilões, os itens e o auditório (o chat depende da configuração do leilão). Não dá lance nem escreve no chat |
| **Equipe Dole Leilão (Super admin)** | Operação da plataforma | **Painel só na v2** (D-28). Na v1, a operação é feita por comandos no servidor |

## Jornada principal

1. O leiloeiro acessa `doleleilao.com.br`, preenche o cadastro (dados pessoais, CPF/CNPJ, nome da empresa) e escolhe o **slug** da loja.
2. Ele entra no painel, que mostra a seção **"Comece seu primeiro leilão"** com 4 passos: (1) completar os dados da empresa, (2) cadastrar produtos, (3) configurar o pagamento (cartão de crédito, obrigatório), (4) cadastrar o leilão (D-42, D-55). No passo 1 ele configura a loja: contato, modelo (relógios, roupa ou eletrônicos), logo, cores e fonte. Banners e categorias são opcionais.
3. Ele cadastra os produtos (fotos, link do YouTube, ficha técnica livre, valor inicial e incremento em R$) e monta um **lote de leilão**: o grupo de itens que serão leiloados naquele dia, com data e hora e as regras de chat.
4. Ele divulga o link `doleleilao.com.br/{slug}`.
5. O participante entra na loja, se cadastra (nome, CPF, e-mail, telefone e, se quiser, foto ou avatar) e valida o e-mail. Com o cadastro completo, ele fica habilitado automaticamente. Ele favorita produtos e recebe alertas.
6. Na hora marcada, o participante entra no **auditório** (`/{slug}/leilao/{leilao}`) e acompanha o pregão de cada item: dá lances manuais, configura lances automáticos e conversa no chat.
7. Ao fim de cada item, o vencedor vê os confetes e a mensagem "Você é o ganhador!", com um aviso sonoro ("plim"). A arrematação aparece em "Minha conta".
8. O leiloeiro vê os arrematantes no painel e envia mensagem ao vencedor (e-mail, in-app ou WhatsApp via `wa.me`) para combinar pagamento e entrega.

## Escopo

### v1 (este planejamento)
- Acesso completo a todas as funcionalidades, sem cobrança.
- Cadastro do leiloeiro, loja com slug, personalização (modelo, logo, 10 cores, 5 fontes, 3 fundos).
- Catálogo (produtos com fotos, vídeo por link do YouTube, ficha técnica livre, categorias, banners, contagem de visualizações).
- Lotes de leilão (os itens do dia) com pregão ao vivo, lance manual, lance automático e chat em tempo real (respostas e emojis).
- Área do participante: cadastro, validação de e-mail, favoritos, próximos leilões, alertas, arrematações, "quer leiloar algo seu? fale com o leiloeiro".
- Painel admin: usuários (com banimento), produtos, leilões, analytics de pessoas online em tempo real, mensagem ao vencedor.
- Site institucional do Dole Leilão.
- Web mobile first. Arquitetura pronta para app nativo.

### v2 (futuro, já considerado na arquitetura)
- **Monetização** (a partir de 01/12/2026, D-47; grátis até 30/11/2026): taxa só sobre o item arrematado, debitada no cartão de crédito do leiloeiro (D-54); em cada lote ele escolhe se assume a taxa ou repassa ao vencedor. Falta o gateway de pagamento (P-10). Faixas:
  | Valor do produto | Taxa |
  |---|---|
  | até R$ 100,00 | 7,5% |
  | de R$ 100,01 a R$ 500,00 | 6% |
  | de R$ 500,01 a R$ 1.000,00 | 5% |
  | acima de R$ 1.000,00 | 2,5% |
- Painel da equipe Dole Leilão (super admin).
- App mobile nas lojas (iOS e Android), com push notifications.
- Pagamento dentro da plataforma (checkout do arremate).
- Retirada do selo "desenvolvido por Doleleilao.com.br" como benefício de plano (decisão D-06).

### Fora de escopo da v1
- Pagamento ou checkout online e emissão de nota fiscal.
- Logística e frete.
- Domínio próprio do leiloeiro (ex.: `leiloesdojoao.com.br`).
- Modo escuro com alternância (decisão D-08).
- Leilão com mais de um vendedor por loja (marketplace).
- Upload e hospedagem de vídeos (só links do YouTube).
- Lance presencial / leilão híbrido (não planejado).
