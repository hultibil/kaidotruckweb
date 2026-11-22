/* Chatbox AI cho hoithem.php: gọi ai/chat_api.php, vẽ thẻ sản phẩm, lỗi mạng thì dùng NPC cũ (chat.js). */
(function () {
  const cfg = window.KAIDO_AI || {};
  const chat = document.getElementById('chat');
  const input = document.getElementById('question');
  if (!chat || !input || !cfg.api) return;
  let busy = false;

  function scrollEnd() { chat.scrollTop = chat.scrollHeight; }
  function bubble(cls, who) {
    const d = document.createElement('div');
    d.className = cls;
    if (who) { const b = document.createElement('b'); b.textContent = who; d.appendChild(b); }
    chat.appendChild(d);
    return d;
  }
  function userBubble(text) { const d = bubble('user-message'); d.textContent = text; scrollEnd(); }

  function cartButton(p, btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      const fd = new FormData();
      fd.append('csrf_token', cfg.cartCsrf); fd.append('product_id', p.id); fd.append('so_luong', '1'); fd.append('ajax', '1');
      fetch(cfg.cartApi, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json().catch(() => ({ ok: false, message: 'Lỗi.' })))
        .then(d => { btn.textContent = d.ok ? '✔ Đã thêm' : (d.message || 'Không thêm được'); if (!d.ok) btn.disabled = false; })
        .catch(() => { btn.textContent = 'Lỗi mạng'; btn.disabled = false; });
    });
  }

  function renderCards(parent, products) {
    if (!products || !products.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'ai-cards';
    products.forEach(function (p) {
      const c = document.createElement('div'); c.className = 'ai-card';
      if (p.image) { const im = document.createElement('img'); im.src = p.image; im.alt = ''; im.loading = 'lazy'; c.appendChild(im); }
      const body = document.createElement('div'); body.className = 'ai-card-body';
      const name = document.createElement('a'); name.className = 'ai-card-name'; name.href = p.url; name.textContent = p.name; body.appendChild(name);
      const price = document.createElement('div'); price.className = 'ai-card-price'; price.textContent = p.price_text + ' · còn ' + p.stock; body.appendChild(price);
      const act = document.createElement('div'); act.className = 'ai-card-actions';
      const view = document.createElement('a'); view.className = 'ai-btn'; view.href = p.url; view.textContent = 'Xem'; act.appendChild(view);
      const add = document.createElement('button'); add.type = 'button'; add.className = 'ai-btn ai-btn-main'; add.textContent = '🛒 + Giỏ'; cartButton(p, add); act.appendChild(add);
      body.appendChild(act); c.appendChild(body); wrap.appendChild(c);
    });
    parent.appendChild(wrap);
  }

  function aiBubble(text, products, failed) {
    const d = bubble('ai-message', 'KAIDO AI');
    const p = document.createElement('p'); p.className = 'ai-text'; p.textContent = text; d.appendChild(p);
    if (failed) d.classList.add('ai-failed');
    renderCards(d, products);
    scrollEnd();
    return d;
  }

  // Lỗi mạng/máy chủ: trả lời bằng NPC từ khóa cũ (chat.js) để khách vẫn có câu trả lời
  function localFallback(q) {
    if (typeof window.kaidoRuleAnswer === 'function') { aiBubble(window.kaidoRuleAnswer(q), [], false); }
    else { aiBubble('Hiện chưa kết nối được trợ lý AI, bạn thử lại sau nhé.', [], true); }
  }

  function ask(q) {
    q = (q || '').trim();
    if (!q || busy) return;
    busy = true; input.disabled = true;
    userBubble(q);
    const typing = bubble('ai-message ai-typing', 'KAIDO AI');
    const tp = document.createElement('p'); tp.textContent = 'Đang suy nghĩ…'; typing.appendChild(tp); scrollEnd();
    const fd = new FormData(); fd.append('action', 'send'); fd.append('csrf', cfg.csrf); fd.append('message', q);
    fetch(cfg.api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json().then(d => ({ r, d })))
      .then(({ r, d }) => {
        typing.remove();
        if (!d.ok) { aiBubble(d.message || 'Có lỗi xảy ra.', [], true); return; }
        if (d.error) console.warn('[KAIDO AI lỗi - chỉ admin thấy]', d.error);
        // AI lỗi mà không phải câu hỏi mua hàng -> dùng NPC từ khóa cũ thay vì câu "AI đang bận"
        if (d.status === 'failed' && (!d.products || !d.products.length)) { localFallback(q); return; }
        aiBubble(d.reply, d.products, d.status === 'failed');
      })
      .catch(() => { typing.remove(); localFallback(q); })
      .finally(() => { busy = false; input.disabled = false; input.focus(); });
  }

  // Ghi đè các hàm của chat.js (nút "Gửi", Enter, câu hỏi đề xuất, Xóa lịch sử)
  window.sendMessage = function () { const q = input.value; input.value = ''; ask(q); };
  window.askQuestion = function (q) { ask(q); };
  window.clearChat = function () {
    const fd = new FormData(); fd.append('action', 'clear'); fd.append('csrf', cfg.csrf);
    fetch(cfg.api, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(() => {});
    chat.innerHTML = '';
    aiBubble('Đã xóa lịch sử chat. Bạn cứ hỏi tiếp nhé!', [], false);
  };

  // Nạp lại lịch sử khi mở/tải lại trang
  fetch(cfg.api + '?action=history', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
      if (!d.ok || !d.items || !d.items.length) return;
      d.items.forEach(function (it) { userBubble(it.q); aiBubble(it.a, it.products, it.status === 'failed'); });
    })
    .catch(() => {});
})();
