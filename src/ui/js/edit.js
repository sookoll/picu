
;(function ($, window, endpoints, undefined) {

    'use strict';

    const request = (url, data = {}, cb = null, type = 'GET') => {
        $.ajax({
            url,
            type,
            data,
            success: (result) => {
                if (typeof cb === 'function') {
                    cb(result)
                }
            },
            error: () => {
                if (typeof cb === 'function') {
                    cb(false)
                }

            }
        });
    }

    const albumUpdate = (data, cb) => {
        request(endpoints.album, data, cb, 'PUT')
    }

    const itemUpdate = (href, data, cb) => {
        request(href, data, cb, 'PUT')
    }

    $('#editor .thumbs').justifiedGallery({
        lastRow: 'justify',
        rowHeight: 250,
        maxRowHeight: 500,
        margins: 4,
        waitThumbnailsLoad: false,
        captions : true,
        cssAnimation:false,
        imagesAnimationDuration: 0,
        captionSettings: {
            animationDuration: 500,
            visibleOpacity: 0.7,
            nonVisibleOpacity: 0.7
        }
    }).on('jg.complete', function (e) {
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

    $('#editor .thumbs a').on('click', (e) => {
        e.preventDefault();
    })
    $("#editor .thumbs .caption").removeClass('hidden');
    $("#editor h1 span").on('keydown', (e) => {
        if (e.which === 13) {
            e.preventDefault();
            const title = $(e.target).text().trim();
            albumUpdate({ title })
        }
    }).on('blur', (e) => {
        const title = $(e.target).text().trim();
        albumUpdate({ title })
    });
    $("#editor header textarea").on('blur', (e) => {
        const description = $(e.target).val().trim();
        albumUpdate({ description })
    });
    $('#editor .viewbox i').on('click', (e) => {
        const itemId = $(e.target).closest('a').data('fid');
        albumUpdate({ cover: itemId }, (result) => {
            if (result !== false) {
                $('#editor .viewbox i').removeClass('glyphicon-star').addClass('glyphicon-star-empty')
                $(e.target).toggleClass('glyphicon-star-empty glyphicon-star')
            }
        })
    })

    $("#editor .thumbs .caption").on('keydown', (e) => {
        if (e.which === 13) {
            e.preventDefault();
            const href = $(e.target).closest('a').attr('href');
            const title = $(e.target).text().trim();
            itemUpdate(href, { title });
        }
    }).on('blur', (e) => {
        const href = $(e.target).closest('a').attr('href');
        const title = $(e.target).text().trim();
        itemUpdate(href, { title });
    });


}(jQuery, window, endpoints));
