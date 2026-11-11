# 09 — Pontos em aberto

Histórico:
- **07/11/2026:** logo e cores do design system fechados (D-01 a D-20).
- **07/11/2026:** P-01 a P-15 respondidos (D-21 a D-35, em [10-decisoes.md](10-decisoes.md)).
- **07/11/2026:** P-16 a P-19 respondidos e P-10 confirmado para a v2 (D-36 a D-40).
- **08/11/2026:** faixas, data de início e regra de cálculo da cobrança definidas (D-47). P-10 segue aberto só no "como cobrar".
- **10/11/2026:** P-10 respondido em parte (D-54): a taxa é debitada no cartão do leiloeiro, o cartão é obrigatório para agendar e "quem paga" é escolhido em cada lote.

## Ainda em aberto

| ID | Pergunta | Situação |
|---|---|---|
| P-10 | Cobrança: o que falta depois da D-54 | (1) qual gateway faz o débito no cartão (o leiloeiro cadastra o cartão de novo quando ele entrar); (2) o que acontece se o débito for recusado (bloquear novos agendamentos?); (3) item com **desistência** depois do leilão ainda paga a taxa?; (4) com "Repassar ao vencedor", como a taxa aparece para o arrematante (auditório, tela do vencedor e mensagem ao vencedor); (5) trocar o "Em breve, mais informações" do site pelo texto final. Observação: com alíquota única por faixa, um item de R$ 1.000,00 paga R$ 50,00 e um de R$ 1.000,01 paga R$ 25,00 |

## Respondidos

| ID | Pergunta | Resposta | Decisão |
|---|---|---|---|
| P-01 | Escala esperada | Inicialmente **5 leilões simultâneos, 500 pessoas por auditório** | D-21 |
| P-02 | O que é "lote" | **O lote é o grupo de itens que serão leiloados naquele dia** (= o leilão). Cada produto dentro dele é um **item** | D-22 |
| P-03 | Habilitação | **Automática**, depois do cadastro feito e dos dados preenchidos | D-23 |
| P-04 | Incremento | **Campo livre em reais** no cadastro do produto (R$ 100, 200, 500…) | D-24 |
| P-05 | WhatsApp para o vencedor | **Abrir link `wa.me`** com a mensagem pronta | D-25 |
| P-06 | Vídeos | **Plataforma armazena só fotos. Vídeos são links do YouTube** | D-26 |
| P-07 | Campos por modelo | **Não fixos**: ficha técnica livre, porque há produtos de vários ramos | D-27 |
| P-08 | Painel super admin | **Não na v1**, implementar na v2 | D-28 |
| P-09 | Troca de slug | Seguir a regra sugerida **+ verificação de unicidade do slug** | D-29 |
| P-10 | Taxas da v2 | Será planejado na v2 (confirmado) | D-40 |
| P-11 | Time | **1 dev + 1 PM/PO** | D-34 |
| P-12 | Hospedagem | **Hostinger ou HostGator** | D-30, D-31 |
| P-13 | CPF do participante | **Obrigatório** | D-32 |
| P-14 | Áudio do ganhador | **Sem locução**: só um aviso sonoro "plim" | D-33 |
| P-15 | Lance presencial (híbrido) | **Fora da v1** e não será planejado agora | D-35 |
| P-16 | Provedor de hospedagem | **HostGator** (plano VPS) | D-36 |
| P-17 | Pico de lances | **20 por segundo** por item | D-37 |
| P-18 | Foto obrigatória? | **Não**. O participante pode escolher um avatar | D-38 |
| P-19 | Lance múltiplo do incremento? | **Não** | D-39 |
