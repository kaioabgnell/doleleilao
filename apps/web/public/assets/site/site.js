/*
  Dole Leilão, site institucional: o que é só desta página (o kit cuida do resto).
  1. Simulador da taxa (a partir de 01/12/2026): lê as faixas da tabela de preços (data-up-to, data-bp, em centavos e
     pontos-base) e mostra a faixa e a taxa do valor digitado. Alíquota única pela faixa do valor (D-47).
  3. Confete da tela do ganhador (auditório): cai uma vez quando o auditório aparece e de novo quando o ponteiro
     entra na foto, como na tela real do participante. Com "reduzir movimento", o CSS não anima nada.
  2. Sessão: quem já entrou no painel vê "Ir para o painel" no lugar de "Entrar" (GET /api/sessao, só a presença do cookie).
*/
const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function setupFee() {
  const form = document.querySelector('[data-fee]');
  const rows = [...document.querySelectorAll('[data-fee-tiers] [data-bp]')];
  if (!form || !rows.length) return;
  const input = form.querySelector('input');
  if (!input) return;
  const out = form.querySelector('[data-fee-out]');
  const tiers = rows.map((r) => ({ upTo: r.dataset.upTo === 'Infinity' ? Infinity : Number(r.dataset.upTo), bp: Number(r.dataset.bp) }));
  const mask = (text) => {
    const digits = text.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
    return digits ? brl.format(Number(digits) / 100).replace(/^R\$\s?/, '') : '';
  };
  const update = () => {
    const cents = Number(input.value.replace(/\D/g, '') || 0);
    if (!cents) { out.textContent = 'Digite o valor do item para ver a taxa.'; return; }
    const tier = tiers.find((t) => cents <= t.upTo) ?? tiers[tiers.length - 1];
    const fee = Math.round((cents * tier.bp) / 10000);
    const rate = (tier.bp / 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
    out.textContent = `Faixa de ${rate}%: taxa de ${brl.format(fee / 100)}`;
  };
  input.addEventListener('input', () => { input.value = mask(input.value); update(); });
  update();
}

async function setupSession() {
  const link = document.querySelector('[data-session-link]');
  if (!link) return;
  try {
    const res = await fetch('/api/sessao', { credentials: 'same-origin' });
    if (!res.ok) return;
    const { painel } = await res.json();
    if (painel) { link.textContent = 'Ir para o painel'; link.href = '/painel'; }
  } catch { /* sem a API, fica "Entrar" */ }
}

function setupConfetti() {
  const stage = document.querySelector('[data-confetti]');
  if (!stage) return;
  const play = () => { stage.classList.remove('is-playing'); void stage.offsetWidth; stage.classList.add('is-playing'); };
  const io = new IntersectionObserver((entries) => {
    if (entries.some((e) => e.isIntersecting)) { play(); io.disconnect(); }
  }, { threshold: 0.5 });
  io.observe(stage);
  stage.addEventListener('pointerenter', play);
}

setupFee();
setupSession();
setupConfetti();
