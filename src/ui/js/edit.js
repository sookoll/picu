
;(function ($, window, endpoints, undefined) {

    'use strict';

    function copy (str) {
        return new Promise((resolve, reject) => {
            try {
                const el = document.createElement('textarea')
                el.value = str
                el.style.position = 'absolute'
                el.style.left = '-9999px'
                document.body.appendChild(el)
                el.select()
                const successful = document.execCommand('copy')
                document.body.removeChild(el)
                if (successful) {
                    resolve()
                } else {
                    reject(Error(successful))
                }
            } catch (err) {
                reject(err)
            }
        })
    }

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

    $('#editor .thumbs a.thumb').on('click', (e) => {
        e.preventDefault();
    })
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
    $('#editor .img-toolbar .cover').on('click', (e) => {
        e.preventDefault();
        const target = $(e.currentTarget);
        const itemId = target.data('fid');
        albumUpdate({ cover: itemId }, (result) => {
            if (result !== false) {
                $('#editor .viewbox .cover').removeClass('active');
                target.addClass('active');
            }
        })
    })

    $("#editor .thumbs .caption").removeClass('hidden').on('keydown', (e) => {
        if (e.which === 13) {
            e.preventDefault();
            const href = $(e.target).closest('.viewbox').find('a.thumb').attr('href');
            const title = $(e.target).text().trim();
            itemUpdate(href, { title });
        }
    }).on('blur', (e) => {
        const href = $(e.target).closest('.viewbox').find('a.thumb').attr('href');
        const title = $(e.target).text().trim();
        itemUpdate(href, { title });
    });

    $('#editor .copy').on('click', (e) => {
        e.preventDefault();
        const url = $(e.target).attr('href');
        copy(url)
            .then(() => {
            })
            .catch(() => {
            })
    })


}(jQuery, window, endpoints));
