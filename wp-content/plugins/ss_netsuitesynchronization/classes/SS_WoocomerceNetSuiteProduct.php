<?php

class SS_WoocomerceNetSuite
{
	public $globalParentCategory = -101;
	public $DB;

	public $categorySyncTable;
	public $listProductSyncTable;

	public $productWpTable;


	public static $associationArray = array(
											'name' => 'storeDisplayName',
											'description' => 'storeDescription',
											'NsId' => 'internalId',
											);



	public static $settingsForSiteType = array(
												/* www.topbookshop.com.au */
												'2' => array(
															'parentCategoryArray' => array(172351)
															),

												/* www.ljharper.com.au */
												'1' => array(
															'parentCategoryArray' => array(17480, 17454, 17455, 17456, 17467, 17468, 17478, 17479)
															)
											);


	public function __construct()
	{
		global $wpdb;
		$this->DB = $wpdb;
		$this->categorySyncTable = $this->DB->prefix . "ss_netSuite_category";
		$this->listProductSyncTable = $this->DB->prefix . "ss_netSuite_listproducts";
		$this->productWpTable = $this->DB->prefix . "ss_netSuite_products";
		$this->settingTable = $this->DB->prefix . "ss_netSuite_settings";
		$this->parentChildTable = $this->DB->prefix . "ss_netSuite_parentAndChild";
	}



	public function saveImageFromUrl ($url = Null, $filename = Null, $content = Null)
	{
		if (empty($url) || empty($filename))
		{
			return false;
		}
		$uploaddir = wp_upload_dir();

		$uploadfile = $uploaddir['path'] . '/' . $filename;

		

		if (empty($content))
			$contents = file_get_contents($url);
		else
			$contents = $content;

		$savefile = fopen($uploadfile, 'w');
		fwrite($savefile, $contents);
		fclose($savefile);
		$wp_filetype = wp_check_filetype(basename($filename), null );
		$attachment = array(
		    'post_mime_type' => $wp_filetype['type'],
		    'post_title' => $filename,
		    'post_content' => '',
		    'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment( $attachment, $uploadfile );
		$imagenew = get_post( $attach_id );
		$fullsizepath = get_attached_file( $imagenew->ID );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
		$res = wp_update_attachment_metadata( $attach_id, $attach_data );
		return $attach_id;
	}



	public function createNewWoocomerceProduct($product = array())
	{
		if (empty($product))
			return false;

		if (!empty($product['memberList']))
		{
			$childItem = $product['memberList']['itemMember'];
			$childItemsId = array();
			foreach ($childItem as $itemChild)
			{
				$childItemsId[$itemChild['item']['internalId']] = $itemChild['quantity'];
			}
		}

		$name = $product[self::$associationArray['name']];
		$description = $product[self::$associationArray['description']];
		if (empty($description))
			$description = '';
		$loginUserId = get_current_user_id();

		
		$price = Null;

		$pricingMatrix = @$product['pricingMatrix']['pricing'];
		if (!empty($pricingMatrix) && gettype($pricingMatrix) == 'array')
		{
			foreach ($pricingMatrix as $pricing)
			{
				if ($pricing['priceLevel']['name'] == 'Online Price' || $pricing['priceLevel']['internalId'] == 5)
				{
					$price = $pricing['priceList']['price'][0]['value'];
				}
			}	
		}

		$NsId = $product[self::$associationArray['NsId']];

		if (isset($product['siteCategoryList']))
		{
			foreach ($product['siteCategoryList']['siteCategory'] as $cat)
			{
				if (!isset($categoryWpId))
				{
					$category = $this->checkExistCategory($cat['category']['internalId']);
					if (!empty($category))
					{
						$categoryWpId = $category[0]->wp_id;
						$termCategory = get_term_by('id', $categoryWpId, 'product_cat');
					}
				}
			}
		}

		$post = array(
		    'post_author' => $loginUserId,
		    'post_content' => $description,
		    'post_status' => "publish",
		    'post_title' => $name,
		    'post_parent' => '',
		    'post_type' => "product",
		);

		$productInDB = $this->checkProductInDb($NsId);
		if (!empty($productInDB))
		{
			$post_id = $productInDB[0]->wp_id;
			$post = array(
							'ID' => $post_id,
							'post_title' => $name,
							'post_content' => $description,
						);
			wp_update_post($post);
		}
		else
		{
			//Create post
			$post_id = wp_insert_post( $post, $wp_error );
		}
		


		if ($post_id)
		{
			if (isset($childItemsId) && !empty($childItemsId))
			{
				$this->saveParentChild($NsId, $childItemsId);
				wp_set_object_terms ($post_id,'composite','product_type');
			}
			else
			{
				wp_set_object_terms ($post_id,'simple','product_type');
			}

			if (!empty($product['storeDisplayImage']) && function_exists('wp_generate_attachment_metadata'))
			{
				$storeImageId = $product['storeDisplayImage']['internalId'];
				$newSiteObject = new NetSuiteSynch;
				$res = $newSiteObject->getFile($storeImageId);

				if ($res)
				{
					$oldImage = get_post_meta($post_id, "_thumbnail_id", true);
					if (!empty($oldImage))
					{
						wp_delete_attachment($oldImage);
					}
					$imageId = $this->saveImageFromUrl($res->url, $res->name, $res->content);
					if (!empty($oldImage))
					{
						update_post_meta( $post_id, '_thumbnail_id', $imageId);
					}
					else
					{
						add_post_meta($post_id, '_thumbnail_id', $imageId);
						update_post_meta($post_id, '_thumbnail_id', $imageId);
					}
				}
			}


			if (isset($categoryWpId) && !empty($categoryWpId) && $categoryWpId > 0)
				wp_set_object_terms($post_id, $termCategory->name, 'product_cat');

			update_post_meta( $post_id, '_regular_price', $price );
			update_post_meta( $post_id, '_price', $price );
			update_post_meta( $post_id, '_visibility', 'visible' );
			update_post_meta( $post_id, '_stock_status', 'instock');
			update_post_meta($post_id, '_sku', $NsId);
			$this->saveProductInDb($NsId, $post_id);
			return $post_id;
		}
		else
		{
			return false;
		}
		
		/*
		if($post_id){
		    $attach_id = get_post_meta($product->parent_id, "_thumbnail_id", true);
		    add_post_meta($post_id, '_thumbnail_id', $attach_id);
		}

		wp_set_object_terms( $post_id, 'Races', 'product_cat' );
		wp_set_object_terms($post_id, 'simple', 'product_type');

		update_post_meta( $post_id, '_visibility', 'visible' );
		update_post_meta( $post_id, '_stock_status', 'instock');
		update_post_meta( $post_id, 'total_sales', '0');
		update_post_meta( $post_id, '_downloadable', 'yes');
		update_post_meta( $post_id, '_virtual', 'yes');
		update_post_meta( $post_id, '_regular_price', "1" );
		update_post_meta( $post_id, '_sale_price', "1" );
		update_post_meta( $post_id, '_purchase_note', "" );
		update_post_meta( $post_id, '_featured', "no" );
		update_post_meta( $post_id, '_weight', "" );
		update_post_meta( $post_id, '_length', "" );
		update_post_meta( $post_id, '_width', "" );
		update_post_meta( $post_id, '_height', "" );
		update_post_meta($post_id, '_sku', "");
		update_post_meta( $post_id, '_product_attributes', array());
		update_post_meta( $post_id, '_sale_price_dates_from', "" );
		update_post_meta( $post_id, '_sale_price_dates_to', "" );
		update_post_meta( $post_id, '_price', "1" );
		update_post_meta( $post_id, '_sold_individually', "" );
		update_post_meta( $post_id, '_manage_stock', "no" );
		update_post_meta( $post_id, '_backorders', "no" );
		update_post_meta( $post_id, '_stock', "" );

		// file paths will be stored in an array keyed off md5(file path)
		$downdloadArray =array('name'=>"Test", 'file' => $uploadDIR['baseurl']."/video/".$video);

		$file_path =md5($uploadDIR['baseurl']."/video/".$video);


		$_file_paths[  $file_path  ] = $downdloadArray;
		// grant permission to any newly added files on any existing orders for this product
		// do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, $downdloadArray );
		update_post_meta( $post_id, '_downloadable_files', $_file_paths);
		update_post_meta( $post_id, '_download_limit', '');
		update_post_meta( $post_id, '_download_expiry', '');
		update_post_meta( $post_id, '_download_type', '');
		update_post_meta( $post_id, '_product_image_gallery', '');
		*/
	}





	public function addChildForParent($parentId = Null, $childrenArray = array())
	{
		if (empty($childrenArray) || empty($parentId ))
			return false;
		
		$array = array(
						'bto_style' => 'single',
						'bto_data' => $childrenArray
						);
		$res = WC_CP_Meta_Box_Product_Data::save_configuration( $parentId, $array );
	}





	public function createWooParentCategory($categoryObject = Null)
	{
		$setting = $this->getSettingFromDB();

		$settingForSite = self::$settingsForSiteType[$setting['getSiteContent']];




		$parentCategoryArray = $settingForSite['parentCategoryArray'];

		if (empty($categoryObject))
			return false;

		$trySaved = true;

		$parent = 0;

		if (!in_array($categoryObject->internalId, $parentCategoryArray))
			return $categoryObject;

		if (!empty($categoryObject->description))
			$description = $categoryObject->description;
		else if (!empty($categoryObject->storeDetailedDescription))
			$description = $categoryObject->storeDetailedDescription;
		else
			$description = '';

		$name = $categoryObject->itemId;
		$slug = $this->createSlug($name);

		$categoryId = $this->checkExistCategory($categoryObject->internalId);
		if (empty($categoryId))
		{
			$cid = wp_insert_term(
							        $name,
							        'product_cat',
							        array(
							            'description'=> $description,
							            'slug' => $slug,
							            'parent' => $parent
							        )
							    );
			if (!isset($cid->errors))
				$this->saveCat($cid['term_id'], $categoryObject->internalId);
			else
				debug($name);
		}
		else
		{
			return true;
		}
		return true;
	}



	public function createNewWooCategory($categoryObject = Null)
	{
		if (empty($categoryObject))
			return false;

		if (!empty($categoryObject->parentCategory))
		{

			$parentId = $this->checkExistCategory($categoryObject->parentCategory->internalId);

			if (empty($parentId))
			{
				return $categoryObject;
			}
			else
			{
				$parentId = array_shift($parentId);
				$parent = $parentId->wp_id;
			}

			if (!empty($categoryObject->description))
				$description = $categoryObject->description;
			else if (!empty($categoryObject->storeDetailedDescription))
				$description = $categoryObject->storeDetailedDescription;
			else
				$description = '';

			$name = $categoryObject->itemId;

			$slug = $this->createSlug($name);

			$categoryId = $this->checkExistCategory($categoryObject->internalId);
			if (empty($categoryId))
			{
				$cid = wp_insert_term(
								        $name,
								        'product_cat',
								        array(
								            'description'=> $description,
								            'slug' => $slug,
								            'parent' => $parent
								        )
								    );
				if (!isset($cid->errors))
					$this->saveCat($cid['term_id'], $categoryObject->internalId);
				else
					debug($name);
			}
			else
			{
				return true;
			}
			return true;
		}

	}



	public function saveCategoryProduct($categoryProduct = array())
	{
		if (empty($categoryProduct))
			return false;
		foreach ($categoryProduct as $product)
		{
			$productId = $product->item->internalId;
			$sql = "SELECT * FROM ".$this->listProductSyncTable." WHERE netsuite_id='".$productId."'";
			$checkInDB = $this->DB->get_results($sql);
			if (empty($checkInDB))
			{
				$sql = "INSERT INTO ".$this->listProductSyncTable." (netsuite_id, active)
						VALUES (".$productId.", '-1')";
				$this->DB->query($sql);
			}
		}

	}



	public function saveParentChild($NsId = Null, $childArray = array())
	{
		if (empty($NsId) || empty($childArray))
			return false;

		$sql = "DELETE FROM ".$this->parentChildTable." WHERE parent=".$NsId;
		$this->DB->query($sql);
		foreach ($childArray as $childNsId => $quantity)
		{
			$sql = "INSERT INTO ".$this->parentChildTable." (parent, child, quantity)
						VALUES (".$NsId.", '".$childNsId."', '".$quantity."')";
			$this->DB->query($sql);
		}
	}


	public function getChildItem($parentId = Null)
	{
		if (empty($parentId))
		{
			return false;
		}
		$sql = "SELECT * FROM ".$this->parentChildTable." WHERE parent=".$parentId;
		return $this->DB->get_results($sql);
	}

	public function checkProductInWpDb($wpId = Null)
	{
		if (empty($wpId))
		{
			return false;
		}
		$sql = "SELECT * FROM ".$this->productWpTable." WHERE wp_id=".$wpId;
		return $this->DB->get_results($sql);
	}


	public function deleteProduct($id = Null)
	{
		if (empty($id))
			return false;
		$sql = "DELETE FROM ".$this->productWpTable." WHERE id=".$id;
		return $this->DB->query($sql);
	}


	public function checkProductInDb($NsId = Null)
	{
		if (empty($NsId))
		{
			return false;
		}
		$sql = "SELECT * FROM ".$this->productWpTable." WHERE netsuite_id=".$NsId;
		return $this->DB->get_results($sql);
	}

	public function saveProductInDb($NsId = Null, $WpId = Null)
	{
		if (empty($NsId) || empty($WpId))
		{
			return false;
		}
		$sql = "SELECT * FROM ".$this->productWpTable." WHERE netsuite_id=".$NsId;
		$result = $this->DB->query($sql);
		if (empty($result))
		{
			$sql = "INSERT INTO ".$this->productWpTable." (netsuite_id, wp_id)
						VALUES (".$NsId.", '".$WpId."')";
			return $this->DB->query($sql);
		}
		return true;
	}


	public function deleteCategoryFromDb($categoryWpId = Null)
	{
		if (empty($categoryWpId))
			return false;

		$sql = "SELECT * FROM ".$this->categorySyncTable." WHERE wp_id=".$categoryWpId;
		$res = $this->DB->get_results($sql);
		if (!empty($res))
		{
			$id = $res[0]->id;
			$sql = "DELETE FROM ".$this->categorySyncTable." WHERE id=".$id;
			return $this->DB->query($sql);
		}
	}




	public function saveCat($wooId = Null, $netId = Null)
	{
		if (empty($wooId) || empty($netId))
			return false;
		$sql = "INSERT INTO ".$this->categorySyncTable." (netsuite_id, wp_id)
						VALUES (".$netId.", '".$wooId."')";
		return $this->DB->query($sql);
	}




	public function checkExistCategory($netsuite_id = Null)
	{
		if (empty($netsuite_id))
			return false;

		$sql = "SELECT * FROM ".$this->categorySyncTable." WHERE netsuite_id=".$netsuite_id;
		return $this->DB->get_results($sql);
	}


	public function getCtegoryCount()
	{
		$sql = "SELECT * FROM ".$this->categorySyncTable;
		$res = $this->DB->get_results($sql);
		$count = count($res);
		return $count;
	}

	public function getAllNetSuiteCategory()
	{
		$sql = "SELECT * FROM ".$this->categorySyncTable;
		$res = $this->DB->get_results($sql);
		$returnArray = array();
		if (!empty($res))
		{
			foreach ($res as $category)
			{
				$returnArray[$category->wp_id] = $category->netsuite_id;
			}
		}
		return $returnArray;
	}

	public function getSettingFromDB()
	{
		$sql = "SELECT * FROM ".$this->settingTable;
		$promoterResult = $this->DB->get_results($sql);
		if (!empty($promoterResult))
		{
			$settingArray = array();
			foreach ($promoterResult as $setting)
			{
				$settingArray[$setting->name] = $setting->value;
			}
			return $settingArray;
		}
		else
		{
			return array();
		}
	}



	public function createSlug($str = Null)
	{
		if (empty($str))
			return $str;

		if (preg_match("|^[\d]+$|", $str))
            $str = 'i ' . $str;
        $str = preg_replace('/[^a-zа-яёA-ZА-ЯЁ0-9 ]+/iu', '', $str);
        $aLetters = array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g',
            'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'cz',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '__',
            'ы' => 'y_', 'ь' => '_', 'э' => 'e_', 'ю' => 'yu',
            'я' => 'ya',' ' => '-'
        );
        $aLettersUP = array('А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Cz',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shh', 'Ъ' => '__',
            'Ы' => 'Y_', 'Ь' => '_', 'Э' => 'E_', 'Ю' => 'Yu',
            'Я' => 'YA'
        );
        $str = strtr($str, $aLetters);
        $str = strtr($str, $aLettersUP);
        return $str;
	}

}