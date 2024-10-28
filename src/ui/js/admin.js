;(function(window, $) {
    'use strict';

    function updateAlbum(url, data) {
        $.ajax({
            url: url,
            type: 'PUT',
            data: data,
            success: function (result) {
                window.location.reload();
            }
        });
    }

    $('.album-delete').on('click', function(e) {
        e.preventDefault();
        $.ajax({
            url: e.target.href,
            type: 'DELETE',
            success: function (result) {
                window.location.reload();
            }
        });
    });

    $('.album-set-private').on('click', function(e) {
        e.preventDefault();
        updateAlbum(e.target.href, {
            public: false,
        })
    });

    $('.album-set-public').on('click', function(e) {
        e.preventDefault();
        updateAlbum(e.target.href, {
            public: true,
        })
    });

    $('#admin .thumbs').on('jg.complete', function (e) {
        $('img.lazy').lazyload({
            threshold : 200,
            effect : 'fadeIn'
        });
    }).on('jg.resize', function (e) {
        $('img.lazy').lazyload({
            threshold : 200,
            effect : 'fadeIn'
        });
    });


    $('.fileupload').each(function () {

        var el = $(this),
            bar = el.closest('.info').find('.bar'),
            timer;

        function blinking(elm) {
            timer = setInterval(blink, 10);
            function blink() {
                elm.fadeOut(800, function() {
                    elm.fadeIn(800);
                });
            }
        }

        el.fileupload({
            url: el.attr('href'),
            dataType: 'json',
            autoUpload : true,
            start: function(e){
                bar.html('<div class="progress-bar"></div>');
            },
            done: function (e, data) {
                //window.location.reload();
            },
            progressall: function (e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                bar.find('.progress-bar').css(
                    'width',
                    progress + '%'
                );
                if (data.loaded === data.total) {
                    blinking(bar.find('.progress-bar'));
                }
            },
            stop: function (e) {
                clearInterval(timer);
                setTimeout(function(){
                    bar.find('.progress-bar').fadeOut('slow', function () {
                    bar.find('.progress-bar').remove();
                    $('#admin a.clear-cache.gallery').trigger('click');
                    });
                }, 300);
            }
        })
            .prop('disabled', !$.support.fileInput)
            .parent().addClass($.support.fileInput ? undefined : 'disabled');
    });

}(window, jQuery));
