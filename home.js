/* Homepage wow-effects: count-up stats, scroll reveal, hero parallax, optional photo. */
document.addEventListener('DOMContentLoaded', () => {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // if a real photo exists at assets/img/hero.jpg, use it instead of the illustration
  const scene = document.getElementById('heroScene');
  if (scene) {
    const test = new Image();
    test.onload = () => scene.classList.add('has-photo');
    test.src = 'assets/img/hero.jpg';
  }

  // scroll reveal
  const targets = document.querySelectorAll('.card, .step, .guarantee, .sec-head, .faq-item, .cta-band');
  targets.forEach((el, i) => { el.classList.add('reveal', 'r' + ((i % 4) + 1)); });
  if (reduce || !('IntersectionObserver' in window)) {
    targets.forEach(el => el.classList.add('in'));
  } else {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.15 });
    targets.forEach(el => io.observe(el));
  }

  // count-up
  const countEls = document.querySelectorAll('[data-count]');
  const runCount = (el) => {
    const target = parseInt(el.getAttribute('data-count'), 10);
    const suffix = el.getAttribute('data-suffix') || '';
    if (reduce) { el.textContent = target + suffix; return; }
    const dur = 1400, start = performance.now();
    const tick = (now) => {
      const p = Math.min(1, (now - start) / dur);
      const val = Math.round(target * (1 - Math.pow(1 - p, 3)));
      el.textContent = val + suffix;
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if ('IntersectionObserver' in window) {
    const io2 = new IntersectionObserver((entries) => {
      entries.forEach(en => { if (en.isIntersecting) { runCount(en.target); io2.unobserve(en.target); } });
    }, { threshold: 0.6 });
    countEls.forEach(el => io2.observe(el));
  } else {
    countEls.forEach(runCount);
  }

  // parallax tilt on hero scene
  if (scene && !reduce && window.matchMedia('(pointer:fine)').matches) {
    const art = scene.parentElement;
    art.addEventListener('mousemove', (e) => {
      const r = art.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      scene.style.transform = `rotateY(${x * 6}deg) rotateX(${-y * 6}deg) translateZ(0)`;
    });
    art.addEventListener('mouseleave', () => { scene.style.transform = ''; });
  }
});
