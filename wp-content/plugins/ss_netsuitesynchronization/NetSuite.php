<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'NetSuiteService.php';

if (!function_exists('debug'))
{
	function debug($var = Null, $exit = true)
	{
		echo '<pre>';
			var_dump($var);
		echo '</pre>';

		if ($exit)
			exit();
	}
}


//$NetSuiteSynch = new NetSuiteSynch;
//debug($NetSuiteSynch->getAllItemsIds($searchId, $page));
//$updateResult = $NetSuiteSynch->updateProduct(307, array('salesDescription' => 'Dynamic Glitz  - Teddy Glitter'));
//debug($updateResult, 0);
//$product = $NetSuiteSynch->getProduct(17480);
//debug($product);





class NetSuiteSynch 
{
	public function getAllItemsIds($searchId = Null, $page = 1)
	{
		$days_to_update = 2;
 		$results_per_page = 100;

 		$service = new NetSuiteService();
 		$service->setSearchPreferences(false, $results_per_page);

	 	$service = new NetSuiteService();
		$service->setSearchPreferences(false, $results_per_page);
		$search = new CustomerSearchBasic();

		$searchDateField = new SearchDateField();
		$searchDateField->operator = "after";
		$searchDateField->searchValue = date('Y-m-d\TH:i:s.000\Z', strtotime('-'.$days_to_update.' days'));
		$search->lastModifiedDate = $searchDateField;

		$searchMultiSelectField = new SearchMultiSelectField();
		$searchValue = new RecordRef();
		$searchValue->type = 'subsidiary';
		$searchValue->internalId = 1;
		setFields($searchMultiSelectField, array('operator' => 'anyOf', 'searchValue' => $searchValue));
		$search->subsidiary = $searchMultiSelectField;

		
		

 		$request = new SearchRequest();
		$request->searchRecord = $search;
		$searchResponse = $service->search($request);


	 	if (!empty($searchId))
		{
			$searchId = $searchResponse->searchResult->searchId;
			$searchMoreWithIdRequest = new SearchMoreWithIdRequest();
		 	$searchMoreWithIdRequest->searchId = $searchId;
		 	$searchMoreWithIdRequest->pageIndex = $page;
		 	$searchResponse = $service->searchMoreWithId($searchMoreWithIdRequest);
	 	}

		$searchId = $searchResponse->searchResult->searchId;
		$curent_page = $searchResponse->searchResult->pageIndex;
		$allPages = $searchResponse->searchResult->totalPages;

		if ($allPages < $curent_page)
		{
			return array('error' => true, 'message' => 'No result');
		}


		$elementIds = array();

		if (gettype($searchResponse->searchResult->recordList->record) == 'array')
		{
			foreach ($searchResponse->searchResult->recordList->record as $key => $record)
			{
				$elementIds[] = $record->internalId;
			}
		}
		return array('error' => false, 'element' => $elementIds, 'page' => $curent_page, 'searchId' => $searchId);
	}


	public function getProduct($id) 
	{
	  $service = new NetSuiteService();
	  $service->setSearchPreferences(false, 1000);
	  $itemInfo = new SearchMultiSelectField();
	  $itemInfo->operator = "anyOf";
	  $itemInfo->searchValue = array('internalId' => $id);
	  $search = new ItemSearchBasic();
	  $search->internalId = $itemInfo;
	  $request = new SearchRequest();
	  $request->searchRecord = $search;
	  $searchResponse = $service->search($request);
	  $products = $searchResponse->searchResult->recordList->record;
	  if (!empty($products) && gettype($products) == 'array')
	  	$products = array_shift($products);
	  return $products;
	}




	public function updateProduct($ItemId = Null, $fieldsArray = array())
	{
		//debug($fieldsArray);
		$product = $this->getProduct($ItemId);
		$className = get_class($product);
		/*
		debug($product);

		foreach ()
		{
			$member = new ItemMember;
		}*/

		if (empty($ItemId) || empty($fieldsArray) || gettype($fieldsArray) != 'array' || empty($product))
			return false;

		//debug($fieldsArray);
		//memberList

		$pricingMatrix = $product->pricingMatrix;
		if (isset($fieldsArray['price']) && !empty($fieldsArray['price']))
		{
			//debug($pricingMatrix);
			$priceArrayForForeach = $pricingMatrix->pricing;
			foreach ($priceArrayForForeach as $key => $priceValue)
			{
				
				if ($priceValue->priceLevel->name == 'Online Price' || $priceValue->priceLevel->internalId == 5 )
				{
					$priceValue->priceList->price[0]->value = $fieldsArray['price'];
					$priceArrayForForeach[$key] = $priceValue;
				}
			}
			$pricingMatrix->pricing = $priceArrayForForeach;
		}

		$service = new NetSuiteService();
		if ($className != 'KitItem')
		{

			$ItemRecord= new InventoryItem();
			$ItemRecord->internalId = $ItemId;
			/* product name */
			if (isset($fieldsArray['storeDisplayName']) && !empty($fieldsArray['storeDisplayName']))
			{
				$ItemRecord->storeDisplayName = $fieldsArray['storeDisplayName'];
			}

			/* product description */
			if (isset($fieldsArray['storeDescription']) && !empty($fieldsArray['storeDescription']))
			{
				$ItemRecord->storeDescription = $fieldsArray['storeDescription'];
			}

			/* product price */
			$ItemRecord->pricingMatrix = $pricingMatrix;
		}
		else
		{
			$ItemRecord= new KitItem();
			$ItemRecord->internalId = $ItemId;
			/* product name */
			if (isset($fieldsArray['storeDisplayName']) && !empty($fieldsArray['storeDisplayName']))
			{
				$ItemRecord->storeDisplayName = $fieldsArray['storeDisplayName'];
			}

			/* product description */
			if (isset($fieldsArray['storeDescription']) && !empty($fieldsArray['storeDescription']))
			{
				$ItemRecord->storeDescription = $fieldsArray['storeDescription'];
			}

			/* product price */
			$ItemRecord->pricingMatrix = $pricingMatrix;

			$memberList = $product->memberList;

			$newItemMember = array();
			$itemsId = array();
			foreach ($memberList->itemMember as $itemInner)
			{
				$saveItem = false;
				$quantity = 1;
				foreach ($fieldsArray['pack'] as $pack)
				{
					if ($itemInner->item->internalId == $pack['itemId'])
					{
						$saveItem = true;
						$quantity = $pack['quantity'];
					}
				}

				if ($saveItem)
				{
					//$itemInner->quantity = $quantity;
					$newItemMember[] = $itemInner;
					$itemsId[] = $itemInner->item->internalId;
				}
			}

			/*
			$createNew = false;
			foreach ($fieldsArray['pack'] as $pack)
			{
				if (!in_array($pack['itemId'], $itemsId))
				{
					$itemsId[] = $pack['itemId'];
					$item_member = array();
					$itemMember = new ItemMember();
					$itemMember->internalId = $pack['itemId'];
					$newItemMember[] = $item_member;
					$createNew = true;
				}
			}
			$memberList->itemMember = $newItemMember;
			if ($createNew)
			{
				debug($memberList->itemMember);
			}
			$ItemRecord->memberList = $memberList;
			*/
		}

		$updateRequest = new UpdateRequest();
		$updateRequest->record = $ItemRecord;
		//debug($service->update($updateRequest));
		return $service->update($updateRequest);
	}


	public function getTrueHost()
	{
 		$params = new GetDataCenterUrlsRequest();
	 	$params->account = NS_ACCOUNT;
 		$response = $service->getDataCenterUrls($params);
	 	return $response->getDataCenterUrlsResult->dataCenterUrls->webservicesDomain;
	}



	public function getCategoryes()
	{
		$service = new NetSuiteService();
        $service->setSearchPreferences(false, 1000, false);
        $search = new SiteCategorySearchAdvanced();
        $searchField = new SearchBooleanField();
        $searchField->operator = "is";
        $searchField->searchValue = "true";
        $search->isInactive = $searchField;
        $request = new SearchRequest();
        $request->searchRecord = $search;
         
        $searchResponse = $service->search($request);
        if (!$searchResponse->searchResult->status->isSuccess) {
           	return false;
        } else {
            return $searchResponse->searchResult->recordList->record;
        }
	}



	public function get($id)
    {   
        $service = new NetSuiteService();
        $service->setSearchPreferences(false, 1000, false);
         
        $search = new ItemSearchAdvanced();
        $search->savedSearchId = $id;  // Your SavedSearch ID.
         
        $request = new SearchRequest();
        $request->searchRecord = $search;
         
        $searchResponse = $service->search($request);
         
        if (!$searchResponse->searchResult->status->isSuccess) {
            echo "SEARCH ERROR";
        } else {
            echo "SEARCH SUCCESS, records found: " . 
                $searchResponse->searchResult->totalRecords . "\n";
        }
    }




    public function getFile ($fileId = Null)
    {
    	if (empty($fileId))
    		return false;

    	$service = new NetSuiteService();
		$request = new GetRequest();
		$request->baseRef = new RecordRef();
		$request->baseRef->internalId = $fileId;
		$request->baseRef->type = "file";
		$getResponse = $service->get($request);
		if (!$getResponse->readResponse->status->isSuccess) {
		    return false;
		} else {
		    return $getResponse->readResponse->record;
		}
    }
}