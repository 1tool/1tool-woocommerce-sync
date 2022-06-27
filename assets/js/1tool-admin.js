jQuery(document).ready(function(){
	
	var km_skip_products = 0
	jQuery("#km_sync_products_btn").click(function(){
		window.km_skip_products = 0
		km_sync_products(km_skip_products);
	});
	 
	function show_preloader(){
		jQuery(".km_preloader").show();
	}
	
	function hide_preloader(){
		jQuery(".km_preloader").hide();
	}
	
	function km_sync_products(skip_products){
		 
		var form_data ={"action":"km_sync_products","skip":skip_products};
		jQuery.ajax({
			url: km.ajaxurl,
			data: form_data,
			type: 'POST',
			dataType: 'json',
			beforeSend:function(){
				show_preloader();
			},
			complete:function(){
					//hide_preloader();
			},
			success : function(data){
				if( data.status == 0 ) {
					alert(data.message);
					hide_preloader();
				}
				
				if( data.status == 1 ) {
					var total_product = parseInt(data.total_product);
					var total_synced = parseInt(data.total_synced);
					var total_skip = parseInt(data.skip);
					window.km_skip_products = total_skip;
					jQuery("#km_message").html('Number of products synced :'+ total_skip +'<br />');
					if(total_product == 10){
						km_sync_products(total_skip);
					}else{
						hide_preloader();
					}
				}	
			},
			error : function(jqXHR){
				console.log('Unexpected error.');
				console.log(jqXHR);
				console.log(jqXHR.responseText);
				km_sync_products(skip_products);
			}
		});
	}
});
