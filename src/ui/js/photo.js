/*
 * ViewBox
 */
var ViewBox = function(){
    this.gallery = [];
    this.is_open = false;
    this.current = null;
    this.th = null;
    this.prev = null;
    this.next = null;
    this.minSpace = 40;
    this.dom_modal = null;
    this.dom_overlay = null;
    this.current_dims = null;

    this.init();
};

ViewBox.prototype = {

    init: function (){

        this.dom_overlay = $('#viewbox');
        this.dom_modal = $('#viewbox .img-container div');
        this.th = $('img.thumbnail');
        var load = this.resize();

    },

    resize: function (width, height){
        if(!this.th)
            return;
        var dims = this.calculateDimensions(width, height);
        this.dom_modal.parent().css({
            'width': dims.w + 'px',
            'height': dims.h + 'px'
        });
        return dims.load;
    },

    parseDimension: function (value) {
        value = parseFloat(value);
        return isFinite(value) && value > 0 ? value : null;
    },

    fitDimensions: function (width, height) {
        var maxW = $(window).width();
        var maxH = $(window).height();
        var newW = width;
        var newH = height;

        if(newH > maxH){
            newW = (maxH * newW) / newH;
            newH = maxH;
        }
        if(newW > maxW){
            newH = (maxW * newH) / newW;
            newW = maxW;
        }

        return {w: newW, h: newH};
    },

    calculateDimensions: function (width, height){
        var maxW = $(window).width();
        var maxH = $(window).height();
        var detectedW = this.parseDimension(width);
        var detectedH = this.parseDimension(height);
        var thW = detectedW || this.parseDimension(this.th[0].naturalWidth);
        var thH = detectedH || this.parseDimension(this.th[0].naturalHeight);
        var vbW = this.parseDimension(this.th.attr('data-vb-w'));
        var vbH = this.parseDimension(this.th.attr('data-vb-h'));
        var detected = detectedW && detectedH;
        var load = false;

        if (!(thW && thH)) {
            thW = vbW || maxW;
            thH = vbH || maxH;
        }

        if(!detected && thW <= maxW && thH <= maxH && vbW && vbH){
            thW = vbW;
            thH = vbH;
            load = true;
        }

        var dims = this.fitDimensions(thW, thH);
        return {w: dims.w, h: dims.h, load: load};
    }
}

;(function ($, window, ViewBox, undefined) {

    'use strict';

    // ViewBox
    var vb = new ViewBox();

    $(window).on('resize',function(){
        vb.resize();
    });

    var fadeout = null;
    vb.dom_overlay.mousemove(function () {
        vb.dom_overlay.find('.tools').stop().fadeIn('fast');
        if (fadeout != null) {
            clearTimeout(fadeout);
        }
        fadeout = setTimeout(function() {
            vb.dom_overlay.find('.tools').stop().fadeOut('fast');
        }, 3000);
    });

}(jQuery, window, ViewBox));
