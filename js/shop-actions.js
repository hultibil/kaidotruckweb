(function () {
  function showMessage(text, ok) {
    let box = document.getElementById('shop-action-message');
    if (!box) {
      box = document.createElement('div');
      box.id = 'shop-action-message';
      box.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:9999;padding:12px 16px;border-radius:10px;color:#fff;box-shadow:0 4px 15px #0004;font-weight:600;';
      document.body.appendChild(box);
    }
    box.textContent = text;
    box.style.background = ok ? '#16834a' : '#bd3434';
    clearTimeout(box._timer);
    box._timer = setTimeout(() => box.remove(), 2600);
  }

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!form.matches('.add-cart-form, .fav-form')) return;
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    const data = new FormData(form);
    data.append('ajax', '1');
    // Form có input name="action", nên form.action bị ghi đè thành HTMLInputElement.
    // Lấy URL trực tiếp từ thuộc tính action của thẻ form.
    fetch(form.getAttribute('action'), { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(async response => {
        const raw = await response.text();
        let payload;
        try { payload = JSON.parse(raw); }
        catch (_) { payload = { ok: false, message: raw.replace(/<[^>]*>/g, '').trim() || 'Phản hồi máy chủ không hợp lệ.' }; }
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Thao tác thất bại.');
        if (form.matches('.fav-form')) {
          const liked = !!payload.liked;
          const action = form.querySelector('input[name="action"]');
          if (action) action.value = liked ? 'remove' : 'add';
          if (button) { button.classList.toggle('on', liked); button.textContent = liked ? '♥ Bỏ yêu thích' : '♡ Yêu thích'; }
        }
        showMessage(payload.message || 'Đã cập nhật.', true);
      })
      .catch(error => showMessage(error.message, false))
      .finally(() => { if (button) button.disabled = false; });
  });
})();
