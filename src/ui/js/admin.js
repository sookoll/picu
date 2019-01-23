;(function(window, $) {
    'use strict';

    $('#admin a.clear-cache').on('click',function(e){
        e.preventDefault();
        $.ajax({
            url:$(this).attr('href'),
            type:'GET',
            dataType:'json'
        })
        .done(function(d) {
            if(d && typeof d == 'object' && d.status == 1) {
                // spieces
                alert('Cache tühi, leht laeb uuesti!');
                window.location.reload(false);
            } else
                alert('Cache tühjendamine ei õnnestunud');
        })
        .fail(function() {
            alert('Cache tühjendamine ei õnnestunud');
        });
    });

    $('.fileupload').each(function () {

        var el = $(this),
            bar = el.closest('li').find('.bar'),
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
                //console.log(data.result);
                var span = el.closest('li').find('.tools span'),
                    number = Number(span.text());
                span.css('font-weight', 'bold');
                span.text(number + 1);
            },
            progressall: function (e, data) {
                var progress = parseInt(data.loaded / data.total * 100, 10);
                bar.find('.progress-bar').css(
                    'width',
                    progress + '%'
                );
                if(data.loaded == data.total){
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

    $('#admin a.change-provider').on('click', function (e){
        e.preventDefault();
        $.ajax({
            url:$(this).attr('href'),
            type:'GET',
            dataType:'json'
        })
        .done(function(d) {
            if(d && typeof d == 'object' && d.status == 1) {
                // spieces
                alert('Albumi koopia tehtud, cache tühi, leht laeb uuesti!');
                window.location.reload(false);
            } else
                alert('Ei õnnestunud');
        })
        .fail(function() {
            alert('Ei õnnestunud');
        });
    });

}(window, jQuery));
