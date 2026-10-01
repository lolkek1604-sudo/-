/* ---- global "reload" banner: shown to every visitor when bank requisites change ---- */
function showReloadBanner() {
  if (document.getElementById('reloadBanner')) return;
  const dict = (window.I18N && window.getLang && window.I18N[window.getLang()]) || {};
  const b = document.createElement('div');
  b.id = 'reloadBanner';
  b.className = 'reload-banner';
  b.innerHTML =
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    + '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>'
    + '<span>' + (dict['reload.msg'] || 'Реквизиты обновлены. Пожалуйста, обновите экран.') + '</span>'
    + '<button type="button" class="reload-btn">' + (dict['reload.btn'] || 'Обновить') + '</button>';
  document.body.appendChild(b);
  b.querySelector('.reload-btn').addEventListener('click', () => location.reload(true));
}

(function watchBanksVersion() {
  let known = null;
  let busy = false;
  const check = () => {
    if (busy) return;                       // don't stack requests
    busy = true;
    return fetch('/api/version.php', { cache: 'no-store' })
      .then(r => r.json())
      .then(d => {
        if (!d || !d.v) return;
        if (known === null) { known = d.v; return; }   // first read = baseline
        if (d.v !== known) showReloadBanner();
      })
      .catch(() => {})
      .finally(() => { busy = false; });
  };
  check();
  setInterval(check, 3000);                 // poll every 3s
  // instant check when the visitor comes back to the tab
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
})();

/* interactions: language switcher, faq accordion */
document.addEventListener('DOMContentLoaded', () => {
  // ---- language ----
  const lang = window.getLang ? window.getLang() : 'ru';
  if (window.applyI18n) window.applyI18n(lang);
  syncLangUI(lang);

  const langBtn = document.getElementById('langBtn');
  const langMenu = document.getElementById('langMenu');
  if (langBtn && langMenu) {
    langBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      langMenu.classList.toggle('open');
    });
    document.addEventListener('click', () => langMenu.classList.remove('open'));
    langMenu.querySelectorAll('button[data-lang]').forEach(b => {
      b.addEventListener('click', () => {
        const l = b.getAttribute('data-lang');
        if (window.applyI18n) window.applyI18n(l);
        syncLangUI(l);
        langMenu.classList.remove('open');
      });
    });
  }

  function syncLangUI(l) {
    const label = document.getElementById('langLabel');
    const names = { ru: 'Русский', uz: 'O‘zbekcha' };
    if (label) label.textContent = names[l] || names.ru;
    document.querySelectorAll('#langMenu button[data-lang]').forEach(b => {
      b.classList.toggle('active', b.getAttribute('data-lang') === l);
    });
  }

  // ---- faq accordion ----
  document.querySelectorAll('.faq-item').forEach(item => {
    const q = item.querySelector('.faq-q');
    const a = item.querySelector('.faq-a');
    q.addEventListener('click', () => {
      const open = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => {
        i.classList.remove('open');
        const aa = i.querySelector('.faq-a');
        if (aa) aa.style.maxHeight = null;
      });
      if (!open) {
        item.classList.add('open');
        a.style.maxHeight = a.scrollHeight + 'px';
      }
    });
  });
});
