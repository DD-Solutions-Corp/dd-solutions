$(function () {
  $(".js-title").on("click", function() {
    $(this).next().slideToggle(200);
    $(this).toggleClass("open",200);
  });
});