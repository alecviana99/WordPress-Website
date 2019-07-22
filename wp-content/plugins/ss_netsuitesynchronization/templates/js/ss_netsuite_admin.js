$('.startSynchronization').on('click', function(e){
	e.preventDefault();
	$('.functionalsButton').hide();
	$('.processSynchronizations').html('Start synchronizations...').show();
	ssNetSuiteSync.productSync();
});


$('.startSynchronizationCategory').click(function(e){
	e.preventDefault();
	$('.functionalsButton').hide();
	$('.processSynchronizations').html('Starting categories synchronization process...').show();
	ssNetSuiteSync.synchronizationsCategories();
});

$('.user_name_box').change(function(e){
    e.preventDefault();
    ssNetSuiteSync.showUserCategory();
});

$('body').on('click','.SShideResult', function(e){
	e.preventDefault();
	SS_hideresult();
});

function SS_hideresult()
{
	$('.functionalsButton').show();
	$('.processSynchronizations').html('').hide();
	ssNetSuiteSync.allSearchProductCount = 0;
	ssNetSuiteSync.allSavedProduct = 0;
	ssNetSuiteSync.elementIdForSync = 0;
	ssNetSuiteSync.elementIdForSyncParent = 0;
	ssNetSuiteSync.productIdsArray = [];
}



var ssNetSuiteSync = {
	returnBackButton : '<span class="SShideResult">Return back</span>',

	allSearchProductCount : 0,
	productIdsArray : [],
	allSavedProduct : 0,

	elementIdForSync : 0,
	elementIdForSyncParent: 0,

	productarrayForSave: [],


	/* Synchronization products from NetSuite step by step */
	/* get 100 id product from NetSuite and synchronization their */
	/* in  saveProductFromNetSuite */
	productSync : function(){
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ss_NetSuite',
				ssNetSuiteAction: 'startSync'
			}
		}).done(function(data){
			if (!data)
			{	
				ssNetSuiteSync.productSync();
			}
			else if (data.error)
			{
				if (ssNetSuiteSync.allSearchProductCount == 0)
				{
					var html = data.message+'<br>'+ssNetSuiteSync.returnBackButton;
				}
				else
				{
					var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Process products synchronization was done.<br/>'+ssNetSuiteSync.returnBackButton;
				}
				if ($('.procesSynchronizationSpan').size())
				{
					$('.procesSynchronizationSpan').html(html);
				}
				else
				{
					$('.processSynchronizations').html('<span class="procesSynchronizationSpan">' + html + '</span><br/><span class="allSynchronSuccessProduct"></span>');
				}
			}
			else if (!data.error)
			{
				ssNetSuiteSync.allSearchProductCount = ssNetSuiteSync.allSearchProductCount + data.element.length;
				data.element.forEach(function(currentValue, index, arr){
					ssNetSuiteSync.productIdsArray.push(currentValue);
				});

				var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Starting process products saved...';
				$('.processSynchronizations').html(html);
				ssNetSuiteSync.saveProductFromNetSuite();
			}
			else
				ssNetSuiteSync.saveProductFromNetSuite();
		}).fail(function(){
			ssNetSuiteSync.productSync();
		});
	},

    showUserCategory: function(){
        var val_cat = $('.user_name_box').val();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ss_NetSuite',
                ssNetSuiteAction: 'checkCategory',
                cat_val: val_cat,
            }
        }).done(function(data){
            $('#ssSettings > div > form > div:nth-child(3)').html('');
            $('#ssSettings > div > form > div:nth-child(3)').html(data.html);
            $('.show_category_adm').show();
        }).fail(function(){
			ssNetSuiteSync.synchParent();
		});
    },
    
	synchParent: function(){
		if (ssNetSuiteSync.elementIdForSyncParent >= ssNetSuiteSync.productIdsArray.length)
		{
			var countSavedChildAndParent = ssNetSuiteSync.elementIdForSyncParent;
			var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved products was done.<br/>Saved child and parent: '+countSavedChildAndParent+'<br/>'+ssNetSuiteSync.returnBackButton;
			$('.processSynchronizations').html(html);
			return false;
		}

		var productId = ssNetSuiteSync.productIdsArray[ssNetSuiteSync.elementIdForSyncParent];
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ss_NetSuite',
				ssNetSuiteAction: 'savedChildForParent',
				productId: productId
			} 
		}).done(function(data){
			if (!data)
			{
				ssNetSuiteSync.synchParent();
			}
			else if (data.error)
			{
				var countSavedChildAndParent = ssNetSuiteSync.elementIdForSyncParent;
				var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved products was done.<br/>Saved child and parent: '+countSavedChildAndParent+'<br/>'+ssNetSuiteSync.returnBackButton;
				$('.processSynchronizations').html(html);
				return true;
			}
			else if (!data.error)
			{
				var countSavedChildAndParent = ssNetSuiteSync.elementIdForSyncParent + 1;
				var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved products was done.<br/>Saved child and parent: '+countSavedChildAndParent+'<br/>';
				$('.processSynchronizations').html(html);

				ssNetSuiteSync.elementIdForSyncParent = ssNetSuiteSync.elementIdForSyncParent + 1;
				ssNetSuiteSync.synchParent();
			}
			else
			{
				ssNetSuiteSync.synchParent();
			}
		}).fail(function(){
			ssNetSuiteSync.synchParent();
		});
	},



	/* get info about product fron NetSuite and saved in DB */
	saveProductFromNetSuite : function(){
		if (ssNetSuiteSync.elementIdForSync >= ssNetSuiteSync.productIdsArray.length)
		{
			var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved products was done.<br/>';
			$('.processSynchronizations').html(html);
			ssNetSuiteSync.synchParent();
		}

		if (ssNetSuiteSync.productIdsArray[ssNetSuiteSync.elementIdForSync])
		{
			var productId = ssNetSuiteSync.productIdsArray[ssNetSuiteSync.elementIdForSync];
		}
		else
		{
			return true;
		}
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ss_NetSuite',
				ssNetSuiteAction: 'getProductInfo',
				productId: productId
			} 
		}).done(function(data){
			if (!data)
			{
				ssNetSuiteSync.saveProductFromNetSuite();
			}
			else if (data.error)
			{
				var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved products was done.<br/>'+ssNetSuiteSync.returnBackButton;
				$('.processSynchronizations').html(html);
				return true;
			}
			else if (!data.error)
			{
				ssNetSuiteSync.saveProduct(data.product);
			}
			else
			{
				ssNetSuiteSync.saveProductFromNetSuite();
			}
		}).fail(function(){
			ssNetSuiteSync.saveProductFromNetSuite();
		});
	},


	/* save one product */
	saveProduct : function(product){
		ssNetSuiteSync.productarrayForSave = product
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ss_NetSuite',
				ssNetSuiteAction: 'savedProduct',
				product: product
			} 
		}).done(function(data){
			if (!data)
			{
				ssNetSuiteSync.saveProduct(ssNetSuiteSync.productarrayForSave);
			}
			else if (data.error)
			{
				var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved products was done.<br/>'+ssNetSuiteSync.returnBackButton;
				$('.processSynchronizations').html(html);
				return true;
			}
			else
			{
				ssNetSuiteSync.allSavedProduct = ssNetSuiteSync.allSavedProduct + 1;
				ssNetSuiteSync.elementIdForSync = ssNetSuiteSync.elementIdForSync + 1;
				var html = 'Was found: ' + ssNetSuiteSync.allSearchProductCount + ' records.<br/>Was saved: '+ssNetSuiteSync.allSavedProduct+'<br/>Process saved product ...';
				$('.processSynchronizations').html(html);
				ssNetSuiteSync.saveProductFromNetSuite();
			}
		}).fail(function(){
			ssNetSuiteSync.saveProduct(ssNetSuiteSync.productarrayForSave);
		});
	},


	/* Synchronization categories from NetSuite */
	synchronizationsCategories : function startSyncCats(){
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ss_NetSuite',
				ssNetSuiteAction: 'syncCats'
			}
		}).done(function(data){
			if (!data)
			{
				ssNetSuiteSync.synchronizationsCategories();
			}
			else if (data.error)
			{
				var resultHtml = 'Error!';
			}
			else
			{
				var resultHtml = 'Categories synchronization was done. Successfully: ' + data.category_count;
			}
			var returnBackButton = '<br>'+ssNetSuiteSync.returnBackButton;
			$('.processSynchronizations').html(resultHtml + returnBackButton);
		}).fail(function(){
			ssNetSuiteSync.synchronizationsCategories();
		});
	},
};