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

    init : function(){

        this.dom_overlay = $('#viewbox');
        this.dom_modal = $('#viewbox .img-container div');
        this.th = $('img.thumbnail');
        var load = this.resize();

    },
    
    resize : function(){
        if(!this.th)
            return;
        var dims = this.calculateDimensions();
        this.dom_modal.parent().css({'width':dims.w+'px','height':dims.h+'px'});
        return dims.load;
    },
    
    calculateDimensions : function(){

        var maxW = $(window).width();
        var maxH = $(window).height();
        var newW = this.th[0].naturalWidth;
        var newH = this.th[0].naturalHeight;

        if(newW > maxW || newH > maxH){
            // if h is bigger
            if(newH > maxH){
                newW = (maxH * newW) / newH;
                newH = maxH;
            }
            // if new width is still bigger
            if(newW > maxW){
                newH = (maxW * newH) / newW;
                newW = maxW;
            }
            load = false
        }
        else {
            newW = this.th.attr('data-vb-w');
            newH = this.th.attr('data-vb-h');
            // if h is bigger
            if(newH > maxH){
                newW = (maxH * newW) / newH;
                newH = maxH;
            }
            // if new width is still bigger
            if(newW > maxW){
                newH = (maxW * newH) / newW;
                newW = maxW;
            }
            load = true
        }
        return {w: newW, h: newH, load: load};
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