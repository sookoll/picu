/*
 * ViewBox
 * swipe: http://www.awwwards.com/touchswipe-a-jquery-plugin-for-touch-and-gesture-based-interaction.html
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
		var th = this;
		$('a.viewbox').each(function(){
			th.gallery.push(th.formatHref($(this).attr('href')));
		});
		
		this.dom_overlay = $('#viewbox');
		this.dom_modal = $('#viewbox .img-container');
		
	},
	
	open : function(href){
		this.dom_modal.html('');
        href = this.formatHref(href);
		href_ = href.split('/');
		
		// fullscreen
		if(href_.length > 0 && href_[href_.length-1] == 'fs'){
			this.toggleFullScreen();
			href_.splice(href_.length-1,1);
			href = href_.join('/');
		}
		
		// already open
		if(this.current == href)
			return;
		
		// find next and prev
		if($.inArray(href,this.gallery) + 1 > this.gallery.length-1)
			this.next = href;
		else
			this.next = this.gallery[($.inArray(href,this.gallery) + 1)];
		if($.inArray(href,this.gallery) - 1 < 0)
			this.prev = href;
		else
			this.prev = this.gallery[($.inArray(href,this.gallery) - 1)];
		
		this.th = $('li[data-id='+href_[3]+'] a.viewbox img');
		var src = this.th.attr('data-original');
		var img = $('<img src="'+src+'" class="" />')
		var load = this.resize();
		this.dom_modal.html(img);
		
		// tools
		var tools = this.dom_overlay.find('.tools');
		tools.find('.title span').html(this.th.closest('a').attr('title'));
		tools.find('a.download').attr('href', this.th.closest('li').find('a.download').attr('href'));
		tools.find('a.full').attr('href', this.th.closest('li').find('a.full').attr('href'));
		
		if(!this.is_open){
			this.dom_overlay.show();
            $('body').addClass('no-scroll');
			this.is_open = true;
		}
		
		this.current = href;
		
		if (load) {
			src = this.th.attr('data-vb-src');
			this.dom_modal.append('<img src="'+src+'" class="" />');
		}
        
        // scroll page if out of view
        this.scrollToView(this.th)
	},
	
	close : function(){
		this.dom_overlay.hide();
        $('body').removeClass('no-scroll');
		this.is_open = false;
		this.dom_modal.html('');
		this.current = null;
		this.next = null;
		this.prev = null;
	},
	
	/*
	 * url length after split wirh / is:
	 * 3 - set
	 * 4 - photo
	 * 5 - fullscreen
	 */
	formatHref : function(href){
		return href.match(/[^/].*[^/]/g)[0];
	},
	
	resize : function(){
		if(!this.th)
			return;
		var dims = this.calculateDimensions();
		this.dom_modal.css({'width':dims.w+'px','height':dims.h+'px'});
		return dims.load 
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
	},

	toggleFullScreen : function(){
		var el = this.dom_overlay[0];
		if(!el.fullScreenEnabled){
			if (el.requestFullscreen) {
				el.requestFullscreen();
			} else if (el.msRequestFullscreen) {
				el.msRequestFullscreen();
			} else if (el.mozRequestFullScreen) {
				el.mozRequestFullScreen();
			} else if (el.webkitRequestFullscreen) {
				el.webkitRequestFullscreen();
			}
		} else {
			if(document.exitFullscreen) {
				document.exitFullscreen();
			} else if(document.msExitFullscreen) {
				document.msExitFullscreen();
			} else if(document.mozCancelFullScreen) {
				document.mozCancelFullScreen();
			} else if(document.webkitExitFullscreen) {
				document.webkitExitFullscreen();
			}
		}
			
		this.dom_overlay.find('.tools a.toggle').toggleClass('hidden')
	},
    
    scrollToView : function(element){
        var offset = element.offset().top;
        if(!element.is(":visible")) {
            element.css({"visiblity":"hidden"}).show();
            var offset = element.offset().top;
            element.css({"visiblity":"", "display":""});
        }

        var visible_area_start = $(window).scrollTop();
        var visible_area_end = visible_area_start + window.innerHeight;

        if(offset < visible_area_start || offset > visible_area_end){
             // Not in view so scroll to it
             $('html,body').animate({scrollTop: offset - window.innerHeight/3}, 1000);
             return false;
        }
        return true;
    }
	
};

;(function ($, window, undefined) {
	
	'use strict';
    
    $.event.special.swipe.scrollSupressionThreshold = 10; // More than this horizontal displacement, and we will suppress scrolling.
    $.event.special.swipe.horizontalDistanceThreshold = 30; // Swipe horizontal displacement must be more than this.
    $.event.special.swipe.durationThreshold = 500; // More time than this, and it isn't a swipe.
    $.event.special.swipe.verticalDistanceThreshold = 75; // Swipe vertical displacement must be less than this.
	
	if (typeof history.pushState === 'undefined'){
		alert('Find some decent browser...');
		return;
	}
	
	$('img.lazy').lazyload({
		threshold : 200,
		effect : 'fadeIn'
	});
	
	$('#set section ul li').hover(function(){
		$(this).find('.tools').toggleClass('hidden');
	});
	
	// ViewBox
	var vb = new ViewBox();
	
	$('body')
		.on('click','a.viewbox, a.full',function(e){
			e.preventDefault();
			var href = $(this).attr('href');
			history.pushState(null, null, href);
			vb.open(href);
		})
		.on('click','a.prev',function(e){
			e.preventDefault();
			if(!vb.is_open)
				return;
			history.pushState(null, null, '/'+vb.prev);
			vb.open(vb.prev);
		})
		.on('click','a.next',function(e){
			e.preventDefault();
			if(!vb.is_open)
				return;
			history.pushState(null, null, '/'+vb.next);
			vb.open(vb.next);
		})
		.on('click','a.vb-close',function(e){
			e.preventDefault();
			if(!vb.is_open)
				return;
			var href = vb.formatHref(location.pathname).split('/');
			href = ['',href[0],href[1],href[2],''].join('/');
			history.pushState(null, null, href);
			vb.close();
		})
        .on('swiperight','#viewbox',function(){
            if(!vb.is_open)
				return;
			history.pushState(null, null, '/'+vb.prev);
			vb.open(vb.prev);
        })
        .on('swipeleft','#viewbox',function(){
            if(!vb.is_open)
				return;
			history.pushState(null, null, '/'+vb.next);
			vb.open(vb.next);
        });
	
	$(window).bind('popstate', function() {
		//var href = location.pathname.replace(/^.*[\\/]/, ""); // get filename only
		var href = vb.formatHref(location.pathname);
		if(href && href.match(/\//g).length > 2)
			vb.open(href);
		else
			vb.close();
	}).trigger('popstate');
	
	
	// navigate with keyboard
	$(document).on('keydown',function(e) {
		switch(e.keyCode){
			case 37:// left
				if(!vb.is_open)
					return;
				e.preventDefault();
				history.pushState(null, null, '/'+vb.prev);
				vb.open(vb.prev);
			break;
			case 38:// up

			break;
			case 39:// right
				if(!vb.is_open)
					return;
				e.preventDefault();
				history.pushState(null, null, '/'+vb.next);
				vb.open(vb.next);
			break;
			case 40:// down

			break;
			case 27:// esc
				if(!vb.is_open)
					return;
				e.preventDefault();
				var href = vb.formatHref(location.pathname).split('/');
				href = ['',href[0],href[1],href[2],''].join('/');
				history.pushState(null, null, href);
				vb.close();
			break;
		}
	});
	
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
	
}(jQuery, window));