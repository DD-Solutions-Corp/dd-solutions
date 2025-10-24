$(function () {
  $('.Toggle').click(function () {
    $(this).toggleClass('active');

    if ($(this).hasClass('active')) {
        $('.NavMenu').addClass('active');
        $('.NavMenu').fadeIn(500);
    } else {
        $('.NavMenu').removeClass('active');
        $('.NavMenu').fadeOut(500);
    }
  });

  $('.navmenu-a').click(function () {
    $('.NavMenu').removeClass('active');
    $('.NavMenu').fadeOut(1000);
    $('.Toggle').removeClass('active');
  });
});


// タブレット以下ナビ消すアクション

if (window.matchMedia('(max-width: 768px)').matches) {
	    //タブレット
$(function () {
  $('.tspNavDel').click(function () {
    $('.NavMenu').removeClass('active');
    $('.NavMenu').fadeOut(500);
    $('.Toggle').removeClass('active');
  });
});

}







