(function(){
  const btn = document.getElementById('pagetop');
  if (!btn) return;
  const onScroll = () => {
    if (window.scrollY > 300) btn.classList.add('is-visible');
    else btn.classList.remove('is-visible');
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();
