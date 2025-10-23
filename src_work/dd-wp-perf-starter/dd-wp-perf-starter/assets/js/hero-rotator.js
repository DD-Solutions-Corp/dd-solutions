/**
 * DD Solutions - ヒーロー画像の軽量ローテーター
 * 使い方：.js-hero-rotator の直下に .hero-slide を複数配置するだけ
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const roots = document.querySelectorAll('.js-hero-rotator');
    if (!roots.length) return;

    roots.forEach((root) => {
      const slides = Array.from(root.querySelectorAll('.hero-slide'));
      if (slides.length <= 1) return;

      let idx = 0;
      const interval = parseInt(root.dataset.interval || '5000', 10);
      let timer = null;

      // 初期状態（CLSを抑えるため、CSSで高さを決めておくのが望ましい）
      slides.forEach((el, i) => {
        el.style.opacity = i === 0 ? '1' : '0';
        el.classList.toggle('is-active', i === 0);
      });

      const next = () => {
        const prev = idx;
        idx = (idx + 1) % slides.length;
        slides[prev].classList.remove('is-active');
        slides[idx].classList.add('is-active');
        slides[prev].style.opacity = '0';
        slides[idx].style.opacity = '1';
      };

      const start = () => { if (!timer) timer = setInterval(next, interval); };
      const stop  = () => { if (timer)  { clearInterval(timer); timer = null; } };

      document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop(); else start();
      });
      root.addEventListener('focusin', stop);
      root.addEventListener('focusout', start);

      // 画像ロード完了後に開始（フェード時のチラつきを抑制）
      const imgs = root.querySelectorAll('img');
      let loaded = 0;
      if (!imgs.length) start();
      imgs.forEach((img) => {
        if (img.complete) {
          loaded++;
        } else {
          img.addEventListener('load', () => { loaded++; if (loaded === imgs.length) start(); });
          img.addEventListener('error', () => { loaded++; if (loaded === imgs.length) start(); });
        }
      });
      if (loaded === imgs.length) start();
    });
  });
})();
