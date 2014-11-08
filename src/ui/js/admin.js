$(function() {
	
	$('#admin header a.clear-cache').on('click',function(e){
		e.preventDefault();
		$.ajax({
			url:$(this).attr('href'),
			type:'GET',
			dataType:'json'
		})
		.done(function(d) {
			if(d && typeof d == 'object' && d.status == 1) {
				// spieces
				alert('Cache tühi');
			} else
				alert('Cache tühjendamine ei õnnestunud');
		})
		.fail(function() {
			alert('Cache tühjendamine ei õnnestunud');
		});
	});
	
});