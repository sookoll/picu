;(function(window, $) {
    'use strict';

    const statusCLasses = {
        OK: 'label-success',
        DELETE: 'label-danger',
        CHANGE: 'label-warning',
        NEW: 'label-primary',
    }

    const showSpinner = (el) => {
        $(el).closest('.info').find('.status .label').addClass('hidden');
        $(el).closest('.info').find('.spinner').removeClass('hidden');
    }

    const hideSpinner = (el) => {
        $(el).closest('.info').find('.status .label').removeClass('hidden');
        $(el).closest('.info').find('.spinner').addClass('hidden');
    }

    const request = (el, url, data = {}, cb = null, type = 'GET') => {
        showSpinner(el);
        $.ajax({
            url,
            type,
            data,
            success: (result) => {
                hideSpinner(el)
                if (typeof cb === 'function') {
                    cb(result)
                }
            },
            error: () => {
                hideSpinner(el)
                if (typeof cb === 'function') {
                    cb(false)
                }

            }
        });
    }

    function iterateImport(list, i) {
        if (i > list.length - 1) {
            return;
        }
        let el = list[i];
        importAlbum(el, () => {
            i += 1
            iterateImport(list, i);
        })
    }

    const importAlbum = (el, cb = null) => {
        request(el, el.href, {}, (result) => {
            if (result !== false && result.length > 0) {
                const album = result[0];
                const type = album.status.type.toUpperCase();
                const albumEl = $(`.album[data-fid="${album.fid}"]`)
                if (albumEl.length) {
                    albumEl.find('.status .label').text(type)
                        .attr('class', 'label ' + statusCLasses[type])
                        .text(type)
                    if (type === 'CHANGE') {
                        const clone = albumEl.find('.items-count > *').clone();
                        clone.find('.photos-count').text(album.status.data.photos);
                        clone.find('.videos-count').text(album.status.data.videos);
                        albumEl.find('.status-items-count').html(clone);
                    }
                    if (type === 'OK') {
                        albumEl.find('.status-items-count').html('');
                        albumEl.find('.items-count').addClass('text-success');
                        albumEl.find('.album-tools').remove();
                    }
                }
                albumEl.find('.status .label')
            }
            if (typeof cb === 'function') {
                cb()
            }
        })
    }

    $('.album-delete').on('click', function(e) {
        e.preventDefault();
        request(e.target, e.target.href, {}, (result) => {
            if (result !== false) {
                $(e.target).closest('.album').remove();
                if ($(e.target).closest('.provider').find('.album').length < 1) {
                    window.location.reload();
                }
            }
        }, 'DELETE')
    });

    $('.album-set-public').on('click', function(e) {
        e.preventDefault();
        const isPublic = $(e.target).data('public');
        request(e.target, e.target.href, { public: !isPublic }, (result) => {
            if (result !== false) {
                $(e.target).closest('.info').find('.status .label')
                    .text(isPublic ? 'PRIVAATNE' : 'AVALIK')
                    .addClass(isPublic ? 'label-danger' : 'label-success')
                    .removeClass(isPublic ? 'label-success' : 'label-danger')
                $(e.target).data('public', isPublic ? 0 : 1).text(isPublic ? 'Määra avalikuks' : 'Määra privaatseks')
            }
        }, 'PUT')
    });

    $('.album-import').on('click', function(e) {
        e.preventDefault();
        importAlbum(e.target);
    })

    $('.album-import-all').on('click', function(e) {
        e.preventDefault();
        var list = []
        $('.album-import').each(function(i, item) {
            list.push(item)
        })
        iterateImport(list, 0);
    })

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
