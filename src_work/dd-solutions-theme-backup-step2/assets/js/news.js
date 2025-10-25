(function($) {
    var page = 1;
    var loading = false;
    
    function loadMore() {
        if (loading) return;
        loading = true;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'load_more_news',
                page: page + 1,
                post_type: $('#newsFilter').val() || 'post'
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $('.news-list').append(response.data.html);
                    page++;
                    sessionStorage.setItem('newsPage', page);
                }
                loading = false;
            }
        });
    }
    
    $(window).on('scroll', function() {
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 100) {
            loadMore();
        }
    });
    
    $(document).ready(function() {
        var savedPage = sessionStorage.getItem('newsPage');
        if (savedPage) page = parseInt(savedPage);
    });
})(jQuery);
