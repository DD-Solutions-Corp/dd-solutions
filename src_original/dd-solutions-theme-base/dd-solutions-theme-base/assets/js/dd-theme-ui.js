
(function($){
  $(function(){
    // ===== Hamburger (match theme's existing markup) =====
    var $btn = $('.Toggle');                // button container
    var $nav = $('.NavMenu.pcNavi');        // target nav
    if ($btn.length && $nav.length){
      // Ensure initial ARIA for accessibility
      $btn.attr({'role':'button','tabindex':'0','aria-expanded':'false','aria-controls':'pc-navi'});
      $nav.attr('id','pc-navi');

      var toggle = function(e){
        if(e){ e.preventDefault(); }
        var open = !$nav.hasClass('is-open');
        $('body').toggleClass('menu-open', open);
        $nav.toggleClass('is-open', open);
        $btn.toggleClass('is-open', open).attr('aria-expanded', open ? 'true' : 'false');
      };

      // Click / Enter / Space
      $btn.on('click', toggle);
      $btn.on('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' '){
          toggle(e);
        }
      });

      // Close when clicking outside (mobile)
      $(document).on('click', function(e){
        if (!$btn.is(e.target) && $btn.has(e.target).length===0 &&
            !$nav.is(e.target) && $nav.has(e.target).length===0){
          if ($nav.hasClass('is-open')) toggle();
        }
      });
    }

    // ===== Pagetop visibility & smooth scroll (kept) =====
    var $pagetop = $('#pagetop');
    if ($pagetop.length){
      var last = 0;
      $(window).on('scroll', function(){
        var y = $(this).scrollTop();
        if (y > 200 && y > last){ $pagetop.fadeIn(200); }
        else if (y < 100){ $pagetop.fadeOut(200); }
        last = y;
      });
      $pagetop.on('click', 'a,button', function(e){
        e.preventDefault();
        $('html,body').animate({scrollTop:0}, 400);
      });
    }
  });
})(jQuery);
