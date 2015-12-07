(function($){
$(document).ready(function() {
	$('[data-toggle="tooltip"]').tooltip();
	$('#reset_option').click(function () {
		$.post(ajaxurl, {action: "woosklad-reset-opt" });
		setTimeout(function() {window.location.reload();}, 1000);
	});
	$('#update_stock').click(function (e) {
		e.preventDefault();
		$('#progress-stock').parent().removeClass('hidden');
		$('#progress-stock').width('100%');
		$('#progress-stock').addClass('progress-bar-success');
		$('#progress-stock').addClass('active');
		$('#progress-stock').text('Получение остатков из "Мой Склад"');
		$.post(ajaxurl,
            {
                action: "woosklad-save-stock"
            },
            function(response){
                if (response.result == "OK") {
					$('#progress-stock').removeClass('progress-bar-success');
					stock_progress();
					$.post(ajaxurl,
						{
							action: "woosklad-update-stock"
						}
					);
					
				}
				if (response.result == 'error') {
					$('#progress-stock').text('Ошибка');
					$('#progress-stock').addClass('progress-bar-danger');
					$('#progress-stock').removeClass('progress-bar-striped');
					$('#progress-stock').removeClass('active');
					$('#progress-stock').width('100%');
					alert(response.message);
				}
            },
            'json'
        );
		
	});
	$('#update_orders').click(function (e) {
		e.preventDefault();
		$('#progress-order').parent().removeClass('hidden');
		$('#progress-order').addClass('active');
		$.post(ajaxurl,
            {
                action: "woosklad-start-orders"
            },
            function(response){
				if (response.result == 'OK') {
					order_progress();
					$.post(ajaxurl,
						{
							action: "woosklad-update-orders"
						}
					);
				}
            },
            'json'
        );
	});
	$('#update_goods').click(function (e) {
		e.preventDefault();
		$('#progress-good').parent().removeClass('hidden');
		$('#progress-good').addClass('active');
		$.post(ajaxurl,
            {
                action: "woosklad-start-goods"
            },
            function(response){
				if (response.result == 'OK') {
					good_progress();
					$.post(ajaxurl,
						{
							action: "woosklad-update-goods"
						}
					);
				}
            },
            'json'
        );
	});
	$('#sync_info').click(function (e) {
		e.preventDefault();
		$('#progress-sync').parent().removeClass('hidden');
		$('#progress-sync').addClass('active');
		$.post(ajaxurl,
            {
                action: "woosklad-synchronization"
            }, function(response){
				if (response.result == 'OK') {
					sync_progress();
					$.post(ajaxurl,
						{
							action: "woosklad-first-sync"
						}
					);
				}
            },
            'json'
        );
	});
});

function stock_progress() {
	$.post(ajaxurl,
		{
			action: "woosklad-stock-progress",
			security: woosklad.stock_progress
		},
		function(response){
			console.log(response);
			if(response.result == 'OK'){

				var progress = (response.count*100) / response.total;
				if (response.count == 0) {
					$('#progress-stock').width('100%');
					$('#progress-stock').text('Подготовка к загрузке');
				}
				else {
					$('#progress-stock').width(progress + '%');
					$('#progress-stock').text(progress.toFixed(1) + '%');
				}
				if(progress < 100) {
					setTimeout(stock_progress, 1000);
				}
				else {
					$('#progress-stock').removeClass('active');
					$('#progress-stock').text('Загрузка завершена');
					$('#last_time_stock').text(response.last_update);
				}
				
			}
		},
		'json'
	);
};

function order_progress(){
	$.post(ajaxurl,
		{
			action: "woosklad-order-progress",
			security: woosklad.order_progress
		},
		function(response){
			console.log(response);
			if(response.result == 'OK'){
				if (response.total==0) {
					$('#progress-order').removeClass('active');
					$('#progress-order').addClass('progress-bar-danger');
					$('#progress-order').width('100%');
					$('#progress-order').text('Нет новых заказов');
				}
				else {
					var progress = (response.count*100) / response.total;
					$('#progress-order').removeClass('progress-bar-danger');
					$('#progress-order').width(progress + '%');
					$('#progress-order').text(progress.toFixed(1) + '%');
					
					if(progress < 100) {
						setTimeout(order_progress, 500);
					}
					else {
						$('#progress-order').removeClass('active');
						$('#progress-order').text('Загрузка завершена');
						$('#last_time_order').text(response.last_update);
					}
				}
				
			}
			if (response.result == 'error') {
				$('#progress-order').text('Ошибка');
				$('#progress-order').addClass('progress-bar-danger');
				$('#progress-order').removeClass('progress-bar-striped');
				$('#progress-order').removeClass('active');
				$('#progress-order').width('100%');
				alert(response.message);
			}
		},
		'json'
	);
};

function good_progress(){
	$.post(ajaxurl,
		{
			action: "woosklad-goods-progress"
		},
		function(response){
			console.log(response);
			if(response.result == 'OK'){
				if (response.total==0) {
					$('#progress-good').removeClass('active');
					$('#progress-good').addClass('progress-bar-danger');
					$('#progress-good').width('100%');
					$('#progress-good').text('Нет новых товаров');
				}
				else {
					var progress = (response.count*100) / response.total;
					$('#progress-good').removeClass('progress-bar-danger');
					$('#progress-good').width(progress + '%');
					$('#progress-good').text(progress.toFixed(1) + '%');
					
					if(progress < 100) {
						setTimeout(good_progress, 500);
					}
					else {
						$('#progress-good').removeClass('active');
						$('#progress-good').text('Загрузка завершена');
						$('#last_time_good').text(response.last_update);
					}
				}
				
			}
			if (response.result == 'error') {
				$('#progress-good').text('Ошибка');
				$('#progress-good').addClass('progress-bar-danger');
				$('#progress-good').removeClass('progress-bar-striped');
				$('#progress-good').removeClass('active');
				$('#progress-good').width('100%');
				alert(response.message);
			}
		},
		'json'
	);
};

function sync_progress() {
	$.post(ajaxurl,
		{
			action: "woosklad-sync-progress"
		},
		function(response){
			console.log(response);
			if(response.result == 'OK'){
				$('#progress-sync').text(response.progress);
				$('#last_time_sync').text(response.last_update);
				if (response.progress != 'Синхронизация завершена') setTimeout(sync_progress, 300);
				else $('#progress-sync').removeClass('active');
			}
			if (response.result == 'error') {
				$('#progress-sync').text('Ошибка');
				$('#progress-sync').addClass('progress-bar-danger');
				$('#progress-sync').removeClass('progress-bar-striped');
				$('#progress-sync').removeClass('active');
				$('#progress-sync').width('100%');
				alert(response.message);
			}
		},
		'json'
	);
}
})(jQuery);