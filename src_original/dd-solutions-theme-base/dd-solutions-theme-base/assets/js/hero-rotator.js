/*
 * シンプルなヒーロースライダー（jQuery 依存）
 * データ属性 data-hero-images="url1,url2,..." に指定された画像を順番に背景として設定し、
 * 6秒ごとに切り替えます。遅延初期化のため IntersectionObserver を利用。
 */
(function($){
  $(function(){
    var $hero = $('[data-hero-images]');
    if (!$hero.length) return;

    var imgs = $hero.data('hero-images').toString().split(',');
    if (!imgs.length) return;

    var idx = 0;
    var interval = 6000;

    function switchSlide(){
      idx = (idx + 1) % imgs.length;
      $hero.css('background-image', 'url(' + imgs[idx].trim() + ')');
    }

    // IntersectionObserver: 要素が表示領域に入ったら開始
    var started = false;
    var observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting && !started){
          started = true;
          // 初期背景を設定
          $hero.css('background-image', 'url(' + imgs[0].trim() + ')');
          setTimeout(function(){
            setInterval(switchSlide, interval);
          }, 2000); // 2秒後に開始
        }
      });
    }, { threshold: 0.1 });
    observer.observe($hero.get(0));
  });
})(jQuery);