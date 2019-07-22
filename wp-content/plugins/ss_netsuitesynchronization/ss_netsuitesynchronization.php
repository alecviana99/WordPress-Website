<?php
/*
	Plugin Name: SS NetSuite Synchronizations
	Description: NetSuite Synchronizations for Wordpress Woocomerce plugin
	Version: 1.1
	Author: SoftSprint
	Author URI: http://softsprint.net/
	Plugin URI: http://softsprint.net/
*/
define('SS_netSuitePlugin_DIR', plugin_dir_path(__FILE__));
define('SS_netSuitePlugin_URL', plugin_dir_url(__FILE__));

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


register_activation_hook(__FILE__, 'ss_netSuite_activation');
register_deactivation_hook(__FILE__, 'ss_netSuite_deactivation');


function ss_netSuite_activation() 
{
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$table_name = $wpdb->prefix . "ss_netSuite_products";
	$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
						  id int(11) NOT NULL AUTO_INCREMENT,
						  netsuite_id int(11) NOT NULL,
						  wp_id int(11) NOT NULL,
						  PRIMARY KEY  (id)
						) $charset_collate;";
	$res = dbDelta( $sql );

	$table_name = $wpdb->prefix . "ss_netSuite_category";
	$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
						  id int(11) NOT NULL AUTO_INCREMENT,
						  netsuite_id varchar(255) NOT NULL,
						  wp_id varchar(255) NOT NULL,
						  PRIMARY KEY  (id)
						) $charset_collate;";
	$res = dbDelta( $sql );


	$table_name = $wpdb->prefix . "ss_netSuite_listproducts";
	$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
						  id int(11) NOT NULL AUTO_INCREMENT,
						  netsuite_id varchar(255) NOT NULL,
						  active varchar(255) NOT NULL,
						  PRIMARY KEY  (id)
						) $charset_collate;";
	$res = dbDelta( $sql );

	$table_name = $wpdb->prefix . "ss_netSuite_settings";
	$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
						  id int(11) NOT NULL AUTO_INCREMENT,
						  name varchar(255) NOT NULL,
						  value varchar(255) DEFAULT '' NOT NULL,
						  PRIMARY KEY  (id)
						) $charset_collate;";
	$res = dbDelta( $sql );


	$table_name = $wpdb->prefix . "ss_netSuite_parentAndChild";
	$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
						  id int(11) NOT NULL AUTO_INCREMENT,
						  parent varchar(255) NOT NULL,
						  child varchar(255) DEFAULT '' NOT NULL,
						  quantity varchar(255) DEFAULT '' NOT NULL,
						  PRIMARY KEY  (id)
						) $charset_collate;";
	$res = dbDelta( $sql );
}
 


function ss_netSuite_deactivation() 
{
    global $wpdb;

	$table_name = $wpdb->prefix . "ss_netSuite_products";
	$sql = 'DROP TABLE ' . $table_name;
	$res = $wpdb->query($sql);

	$table_name = $wpdb->prefix . "ss_netSuite_settings";
	$sql = 'DROP TABLE ' . $table_name;
	$res = $wpdb->query($sql);

	$table_name = $wpdb->prefix . "ss_netSuite_category";
	$sql = 'DROP TABLE ' . $table_name;
	$res = $wpdb->query($sql);

	$table_name = $wpdb->prefix . "ss_netSuite_listproducts";
	$sql = 'DROP TABLE ' . $table_name;
	$res = $wpdb->query($sql);

	$table_name = $wpdb->prefix . "ss_netSuite_parentAndChild";
	$sql = 'DROP TABLE ' . $table_name;
	$res = $wpdb->query($sql);
}














$SoftSprintNetSuiteSynch = new SoftSprintNetSuiteSynch();


class SoftSprintNetSuiteSynch 
{
	public $viewDir;


	public function __construct()
	{
		require_once dirname(__FILE__).'/NetSuite.php';
		require_once dirname(__FILE__).'/classes/SS_WoocomerceNetSuiteProduct.php';

		$this->viewDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR;
		$this->templatesDir = SS_netSuitePlugin_URL . 'templates/';

		add_action('admin_menu', function(){
			add_menu_page( 'NetSuite Synch', 'NetSuite', 'manage_options', 'ss-netsuite', array( $this, 'adminSynchPage' ), SS_netSuitePlugin_URL.'logo.png', 71 );
		} );


		add_action('before_delete_post', array($this, 'deleteProduct'));
		add_action('save_post', array($this, 'updateProduct'));

		add_action('delete_term_taxonomy',array($this, 'deleteCategory'));

		add_action( 'admin_enqueue_scripts', array( $this, 'loadStyleAndScripts' ) );
		add_action('wp_ajax_ss_NetSuite', array($this, 'checkAjax'));
        

		/* for curlJub */
		add_action('wp', array($this, 'curlSynchronization'));

		//$productId = 16356;
		//$newSiteObject = new NetSuiteSynch;
		//$NsProduct = $newSiteObject->getProduct($productId);
		//debug($NsProduct);

		/* category filter */
		add_filter('woocommerce_product_categories_widget_args', array($this, 'showCategoryInFront'));
		/* new field in checkout page */
		add_filter( 'woocommerce_checkout_fields' , array($this, 'newFieldOnCheckout') );
		/* check if not empty */
		add_action('woocommerce_checkout_process', array($this, 'checkUsernameOnOrder'));
		/* save in DB */
		add_action( 'woocommerce_checkout_update_order_meta', array($this, 'saveUserNameForOrder') );
		/* print in admin panel */
		add_action( 'woocommerce_admin_order_data_after_billing_address', array($this, 'echoUserNameInAdminPanel'), 10, 1 );
	}




	public function echoUserNameInAdminPanel($order)
	{
	    echo '<p><strong>'.__('User Name').':</strong> ' . get_post_meta( $order->id, 'user_name', true ) . '</p>';
	}

	public function saveUserNameForOrder( $order_id ) 
	{
	    if ( ! empty( $_POST['user_name'] ) ) {
	        update_post_meta( $order_id, 'user_name', sanitize_text_field( $_POST['user_name'] ) );
	    }
	}

	public function checkUsernameOnOrder() 
	{
	    if ( ! $_POST['user_name'] )
	        wc_add_notice( __( 'Please enter User Name.' ), 'error' );
	}

	public function newFieldOnCheckout( $fields ) 
	{
		$bilirngFields = $fields['billing'];
		$fields['billing'] = array();
		$newBilllingFields = array();
		$newBilllingFields['user_name'] = array('required' => true, 'label' => 'User name', 'placeholder' => 'Write you user name', 'class' => array('form-row-wide'), 'priority' => 10);
		foreach ($bilirngFields as $key => $value)
		{
			$newBilllingFields[$key] = $value;
		}
		$fields['billing'] = $newBilllingFields;
     	return $fields;
	}


	public function showCategoryInFront($args, $instance = Null)
	{
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$UserId = get_current_user_id();
		$allCategoryFromDB = $NetWpProduct->getAllNetSuiteCategory();
		$categoryArray = array();
		foreach ($allCategoryFromDB as $key => $value)
		{
			$categoryArray[] = $key;
		}
		$exclude = array();
		if ($UserId > 0)
		{
			$array_category_check = get_user_meta($UserId, 'key_of_cat', true);
			if (!empty($array_category_check))
			{
				foreach ($categoryArray as $categoryId) 
				{
					if (!in_array($categoryId, $array_category_check))
					{
						$exclude[] = $categoryId;
					}
				}
			}
			else
			{
				$setting = $this->getSetting();
				if (isset($setting['category_fo_every']))
				{
					$array_category_check = json_decode($setting['category_fo_every'], true);
					if (!empty($array_category_check))
					{
						foreach ($categoryArray as $categoryId) 
						{
							if (!in_array($categoryId, $array_category_check))
							{
								$exclude[] = $categoryId;
							}
						}
					}
				}
			}
		}
		else
		{
			$setting = $this->getSetting();
			if (isset($setting['category_fo_every']))
				{
					$array_category_check = json_decode($setting['category_fo_every'], true);
					if (!empty($array_category_check))
					{
						foreach ($categoryArray as $categoryId) 
						{
							if (!in_array($categoryId, $array_category_check))
							{
								$exclude[] = $categoryId;
							}
						}
					}
				}
		}
		$args['exclude'] = $exclude;
		return $args;
	}
	


	public function adminSynchPage()
	{
		if (isset($_POST["user_name"]))
		{
			$user_id = (int) $_POST["user_name"];
			unset($_POST["user_name"]);
			$array_category = array();
			$array_category = $_POST["category"];
			unset($_POST["category"]);
			if ($user_id == 0)
			{
				$this->saveFoEverySetting($array_category);
			}
			else
			{
				update_user_meta( $user_id, 'key_of_cat', $array_category );
			}
			
		}
		if (!empty($_POST))
		{
			$this->saveSetting($_POST);
		}
	    $cate_name = 'key_of_cat';
	    $array_category_check = get_user_meta($user_id, $cate_name, true);

		global $wpdb;
    	$table_name = $wpdb->prefix . "users";
		$sql = "SELECT * FROM ".$table_name;
		$list_user = $wpdb->get_results($sql);
		
		$settings = $this->getSetting();
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$allCategoryFromDB = $NetWpProduct->getAllNetSuiteCategory();
		$categoryForPrint = array();
		$array_forView = array();
		$categoryHtml = '';
		if (!empty($allCategoryFromDB))
		{
			foreach ($allCategoryFromDB as $catedoryWpId => $NsCategoryId)
			{
				$termCategory = get_term_by('id', $catedoryWpId, 'product_cat');
				if (!empty($termCategory))
				{
					$categoryForPrint[$termCategory->term_id] = array(
																		'name' => $termCategory->name,
																		'parent' => $termCategory->parent,
																		'count' => $termCategory->count
																	);
				}
			}
			if (!empty($categoryForPrint))
			{
				foreach ($categoryForPrint as $key => $category)
				{
					$categoryForPrint[$key]['child'] = $this->createTree($categoryForPrint, $key);
				}
				foreach ($categoryForPrint as $key => $category)
				{
					if ($category['parent'] == 0)
					{
						if (isset($category['child']) && !empty($category['child']))
						{
							foreach ($category['child'] as $key => $child)
							{
								$category['child'][$key]['child'] = $this->getChild($child['id'], $categoryForPrint);
							}
						}
						$array_forView[] = $category;
					}
				}
			}
			if (!empty($array_forView))
			{
				$categoryHtml = $this->createHtmlFromArray($array_forView);
				$all_category = $this->createHtmlSecondForm($array_forView);
				$cat_user = $array_category_check;
			}
			
		}
		include $this->viewDir . 'adminSynchPage.php';
	}



	public function createHtmlFromArray($array)
	{	
		$html = '<ul class="synchCategorySS">';
			foreach ($array as $item)
			{
				$html .= '<li>'.$item['name'];
				if (isset($item['child']) && !empty($item['child']))
					$html .= $this->createHtmlFromArray($item['child']);
				$html .= '</li>';
			}
		$html .= '</ul>';
		return $html;
	}
	
	public function createHtmlSecondForm($array, $user_id_in = 2)
	{
	    $user_id = $user_id_in;
	    $cate_name = 'key_of_cat';
	    if ($user_id > 0)
	    {
	    	$array_category_check = get_user_meta($user_id, $cate_name, true);
	    }
	    else
	    {
	    	$setting = $this->getSetting();
	    	$array_category_check = array();
	    	if (isset($setting['category_fo_every']))
	    	{
	    		$array_category_check = json_decode($setting['category_fo_every'], true);
	    	}
	    }	
    	$html = '<ul class="synchCategorySS show_category_adm">';
	        foreach ($array as $key => $item)
	        {
	          $html .= '<li><input type="checkbox"';
	          if (in_array($item['id'], $array_category_check)){
	              $html .= 'checked';
	          }
	          $html .= ' name="category[]" value="'.$item['id'].'">'.$item['name'];
	          if (isset($item['child']) && !empty($item['child']))
	              $html .= $this->createHtmlSecondForm($item['child'], $user_id);
	          $html .= '</li>';
	        }
	     
    	$html .= '</ul>';
	    return $html;
	}


	public function getChild($id, $categoryForPrint)
	{
		$childArray = array();
		if (isset($categoryForPrint[$id]) && isset($categoryForPrint[$id]['child']))
		{
			$childArray = $categoryForPrint[$id]['child'];
			foreach ($childArray as $key => $child)
			{
				$childArray[$key]['child'] = $this->getChild($child['id'], $categoryForPrint);
			}
		}
		return $childArray;
	}


	public function createTree($array = array(), $parentId = Null)
	{
		$childArray = array();
		foreach ($array as $key => $item)
		{
			if ($item['parent'] == $parentId)
			{
				$item['id'] = $key;
				$childArray[] = $item;
			}
		}
		return $childArray;
	}

	public function saveSetting($settingArray = array())
	{
		if (empty($settingArray))
			return false;

		global $wpdb;
		$table_name = $wpdb->prefix . "ss_netSuite_settings";

		$settings = $this->getSetting();
		foreach ($settingArray as $name => $value) 
		{
			if (!isset($settings[$name]))
			{
				$sql = "INSERT INTO ".$table_name." (name, value)
					VALUES ('".$name."', '".$value."')";
			}
			else
			{
				$sql = 'UPDATE '.$table_name.' SET value = "'.$value.'" WHERE name = "'.$name.'"';
			}
			$res = $wpdb->query($sql);
		}
	}


	public function saveFoEverySetting($categoryArray = array())
	{
		if (empty($categoryArray))
			return false;

		global $wpdb;
		$table_name = $wpdb->prefix . "ss_netSuite_settings";
		$value = addslashes(json_encode($categoryArray));
		$settings = $this->getSetting();
		if (!isset($settings['category_fo_every']))
		{
			$sql = "INSERT INTO ".$table_name." (name, value)
				VALUES ('category_fo_every', '".$value."')";
		}
		else
		{
			$sql = 'UPDATE '.$table_name.' SET value = "'.$value.'" WHERE name = "category_fo_every"';
		}
		
		$res = $wpdb->query($sql);
	}

	public function getSetting()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "ss_netSuite_settings";
		$sql = "SELECT * FROM ".$table_name;
		$promoterResult = $wpdb->get_results($sql);
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


	public function deleteCategory($termId)
	{
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$NetWpProduct->deleteCategoryFromDb($termId);
	}


	public function deleteProduct($post_id)
	{
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$productInDB = $NetWpProduct->checkProductInWpDb($post_id);
		if (!empty($productInDB))
		{
			$res = $NetWpProduct->deleteProduct($productInDB[0]->id);
		}
	}	


	public function updateProduct($post_id)
	{
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$productInDB = $NetWpProduct->checkProductInWpDb($post_id);
		if (!empty($productInDB))
		{
			$NsProductId = $productInDB[0]->netsuite_id;

			$assocArray = $NetWpProduct::$associationArray;
			$NsArrayForSaved = array();

			$name = get_the_title($post_id);

			$content_post = get_post($post_id);
			$content = $content_post->post_content;
			$content = apply_filters('the_content', $content);
			$content = str_replace(']]>', ']]&gt;', $content);
			$description = $content;
			$price = get_post_meta($post_id, "_price", true);


			
			$composits = get_post_meta($post_id, "_bto_data", true);
			$pack = array();
			if (!empty($composits))
			{
				foreach ($composits as $composite)
				{
					$compositInDB = $NetWpProduct->checkProductInWpDb($composite['default_id']);
					if (!empty($compositInDB))
					{
						$pack[] = array(
											'itemId' => $compositInDB[0]->netsuite_id,
											'quantity' => $composite['quantity_min'],
											'title' => $composite['title']
										);
					}
				}
			}
			$NsArrayForSaved = array(
										'storeDisplayName' => $name,
										'price' => $price,
										'storeDescription' => $description,
										'pack' => $pack
									);
			$newSiteObject = new NetSuiteSynch;
			$newSiteObject->updateProduct($NsProductId, $NsArrayForSaved);
		}
		
	}

	public function loadStyleAndScripts()
	{
		wp_enqueue_style( 'ss_netsuite_all_style', $this->templatesDir . 'css/ss_netsuite_all_style.css', array(), time() );
		wp_enqueue_script('ss_views_functions', $this->templatesDir . 'js/ss_views_functions.js', array(), time(), true);
		wp_enqueue_script('ss_netsuite_admin', $this->templatesDir . 'js/ss_netsuite_admin.js', array(), time(), true);
	}











	public function curlSynchronization()
	{
		if (isset($_GET['curlsynch']))
		{
			$updateFiles = 0;
			$newSiteObject = new NetSuiteSynch;
			$NetWpProduct = new SS_WoocomerceNetSuite;
			$updateItem = $newSiteObject->getAllItemsIds();
			if (!empty($updateItem['element']))
			{
				foreach ($updateItem['element'] as $NsID)
				{
					$parentProduct = $NetWpProduct->checkProductInDb($NsID);
					if (!empty($parentProduct))
					{
						$_POST['productId'] = $NsID;
						$product = $this->getProductInfo(false);
						if (!empty($product))
						{
							$product = json_decode($product, true);
							$_POST['product'] = $product['product'];
							$res = $this->savedProduct(false);
							if ($res)
								$updateFiles++;
						}
					}
				}
			}
			echo json_encode(array('productWasUpdate'=>$updateFiles));
			exit();
		}
	}

















	/**********************************************************/
	/************** function for ajax *************************/
	/**********************************************************/

	public function checkAjax()
	{
		if (isset($_POST['ssNetSuiteAction']))
		{
			$action = trim($_POST['ssNetSuiteAction']);
			$this->$action();
		}
	}

    public function checkCategory()
    {
    	$user_id = (int) $_POST['cat_val'];
       	if ($user_id == -1)
    	{
    		$html = '';
    		echo json_encode(array('html'=>$html));
			wp_die();
    	}
    	
        if (!isset($_POST['cat_val']))
        {
            echo json_encode(array('error' => true));
			exit();
        }
        
        $NetWpProduct = new SS_WoocomerceNetSuite;
		$allCategoryFromDB = $NetWpProduct->getAllNetSuiteCategory();
		$categoryForPrint = array();
		$array_forView = array();
		$categoryHtml = '';
		if (!empty($allCategoryFromDB))
		{
			foreach ($allCategoryFromDB as $catedoryWpId => $NsCategoryId)
			{
				$termCategory = get_term_by('id', $catedoryWpId, 'product_cat');
				if (!empty($termCategory))
				{
					$categoryForPrint[$termCategory->term_id] = array(
																		'name' => $termCategory->name,
																		'parent' => $termCategory->parent,
																		'count' => $termCategory->count,
																		'id' => $termCategory->term_id
																	);
				}
			}
			if (!empty($categoryForPrint))
			{
				foreach ($categoryForPrint as $key => $category)
				{
					$categoryForPrint[$key]['child'] = $this->createTree($categoryForPrint, $key);
				}
				foreach ($categoryForPrint as $key => $category)
				{
					if ($category['parent'] == 0)
					{
						if (isset($category['child']) && !empty($category['child']))
						{
							foreach ($category['child'] as $key => $child)
							{
								$category['child'][$key]['child'] = $this->getChild($child['id'], $categoryForPrint);
							}
						}
						$array_forView[] = $category;
					}
				}
			}
			
		}

	    $cate_name = 'key_of_cat';
	    
	    if ($user_id > 0)
	    {
	    	$array_category_check = get_user_meta($user_id, $cate_name, true);
	    }
	    else
	    {
	    	$setting = $this->getSetting();
	    	$array_category_check = array();
	    	if (isset($setting['category_fo_every']))
	    	{
	    		$array_category_check = json_decode($setting['category_fo_every'], true);
	    	}
	    }	

    	$html = '<ul class="synchCategorySS show_category_adm">';
    	$array_check = array();
	        foreach ($array_forView as $key => $item)
	        {
	          $html .= '<li><input type="checkbox"';
	          if (in_array($item['id'], $array_category_check)){
	             
	              $html .= 'checked';
	          }
	          $html .= ' name="category[]" value="'.$item['id'].'">'.$item['name'];
	          if (isset($item['child']) && !empty($item['child']))
	              $html .= $this->createHtmlSecondForm($item['child'], $user_id);
	          $html .= '</li>';
	         
	        }
    	$html .= '</ul>';
    	echo json_encode(array('html'=>$html));
		wp_die();
    }

	public function savedChildForParent()
	{
		if (!isset($_POST['productId']))
		{
			echo json_encode(array('error' => true));
			exit();
		}

		$productNsId = $_POST['productId'];
		$NetWpProduct = new SS_WoocomerceNetSuite;

		$parentProduct = $NetWpProduct->checkProductInDb($productNsId);
		
		if (empty($parentProduct))
		{
			echo json_encode(array('error' => true));
			exit();
		}

		$parentProductWpId = $parentProduct[0]->wp_id;

		$childItemNs = $NetWpProduct->getChildItem($productNsId);
		if (!empty($childItemNs))
		{
			$childrenArray = array();
			$i = 0;
			foreach ($childItemNs as $child)
			{
				$childId = $child->child;
				$childInDb = $NetWpProduct->checkProductInDb($childId);
				if (!empty($childInDb))
				{
					
					$childWpId = $childInDb[0]->wp_id;
					$title = get_the_title($childWpId);
					$description = $name;
					$childrenArray[] = array(
												'title' => $title,
												'position' => $i,
												'description' => $description,
												'thumbnail_id' => '',
												'query_type' => 'product_ids',
												'assigned_ids' => array($childWpId),
												'selection_mode' => 'dropdowns',
												'quantity_min' => 1,
												'quantity_max' => 1,
												'discount' => '',
												'hide_subtotal_product' => 1,
												'hide_subtotal_cart' => 1,
												'hide_subtotal_orders' => 1,
											);
				}
				else
				{
					$_POST['productId'] = $childId;
					$product = $this->getProductInfo(false);
					if (!empty($product))
					{
						$productArray = json_decode($product, true);
						$productArray = $productArray['product'];
						$_POST['product'] = $productArray;
						$res = $this->savedProduct(false);
						$childInDb = $NetWpProduct->checkProductInDb($childId);
						$childWpId = $childInDb[0]->wp_id;
						$title = get_the_title($childWpId);
						$description = $name;
						$childrenArray[] = array(
													'title' => $title,
													'position' => $i,
													'description' => $description,
													'thumbnail_id' => '',
													'query_type' => 'product_ids',
													'assigned_ids' => array($childWpId),
													'selection_mode' => 'dropdowns',
													'quantity_min' => 1,
													'quantity_max' => 1,
													'discount' => '',
													'hide_subtotal_product' => 1,
													'hide_subtotal_cart' => 1,
													'hide_subtotal_orders' => 1,
												);
					}
				}
				$i++;
			}
			$NetWpProduct->addChildForParent($parentProductWpId, $childrenArray);
		}
		echo json_encode(array('error' => false));
		exit();
	}


	public function startSync()
	{
		$newSiteObject = new NetSuiteSynch;
		$NetWpProduct = new SS_WoocomerceNetSuite;

		
		$allCategoryFromDB = $NetWpProduct->getAllNetSuiteCategory();
		if (empty($allCategoryFromDB))
		{
			echo json_encode(array('error' => true, 'message' => 'Before start synchronization product, please do synchronization categories!'));
			exit();
		}

		$cattegoriesObjectArray = $newSiteObject->getCategoryes();

		$itemArray = array();

		foreach ($cattegoriesObjectArray as $categoryObject)
		{
			if (in_array($categoryObject->internalId, $allCategoryFromDB))
			{
				if (!empty($categoryObject->presentationItemList))
				{
					$items = $categoryObject->presentationItemList->presentationItem;
					foreach ($items as $item)
					{
						$itemArray[] = $item->item->internalId;
					}
				}
				 
			}
		}

		//$itemArray = array('24244');
		echo json_encode(array('error' => false, 'element' => $itemArray));
		exit();
	}


	public function getProductInfo($exit = true)
	{
		if (!isset($_POST['productId']) || empty($_POST['productId']))
		{
			echo json_encode(array('error' => true));
			exit();
		}
		$productId = (int) $_POST['productId'];
		$newSiteObject = new NetSuiteSynch;
		$NsProduct = $newSiteObject->getProduct($productId);
		if (empty($NsProduct))
		{
			echo json_encode(array('error' => true));
			exit();
		}
		if (!$exit)
		{
			return json_encode(array('error' => false, 'product' => $NsProduct));
		}
		echo json_encode(array('error' => false, 'product' => $NsProduct));
		exit();
	}


	public function savedProduct($exit = true)
	{
		if (!isset($_POST['product']) || empty($_POST['product']))
		{
			echo json_encode(array('error' => true));
			exit();
		}
		$product = $_POST['product'];
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$res = $NetWpProduct->createNewWoocomerceProduct($product);
		if (!$exit)
		{
			return $res;
		}
		echo json_encode(array('error' => false));
		exit();
	}


	public function syncCats()
	{
		$newSiteObject = new NetSuiteSynch;
		$NetWpProduct = new SS_WoocomerceNetSuite;
		$cattegoriesObjectArray = $newSiteObject->getCategoryes();
		$nextStep = array();
		if (!empty($cattegoriesObjectArray))
		{
			foreach ($cattegoriesObjectArray as $key => $categoryObject)
			{
				if ($categoryObject->isOnline)
				{
					$res = $NetWpProduct->createWooParentCategory($categoryObject);
					if ($res !== true && !empty($res))
						$nextStep[] = $res;
				}
				else
				{
					unset($cattegoriesObjectArray[$key]);
				}
			}
			if (!empty($nextStep) && count($cattegoriesObjectArray) != count($nextStep))
			{
				$this->createCatNexStep($nextStep);
				$allCategories = $NetWpProduct->getCtegoryCount();
				echo json_encode(array('error' => false, 'category_count' => $allCategories));
			}
			else
			{	
				$allCategories = $NetWpProduct->getCtegoryCount();
				echo json_encode(array('error' => false, 'category_count' => $allCategories));
			}
		}
		else
		{
			echo json_encode(array('error' => true, 'category_count' => $allCategories));
		}
		exit();
	}




	public function createCatNexStep($categoryArray = array())
	{
		if (empty($categoryArray))
			return true;

		$NetWpProduct = new SS_WoocomerceNetSuite;
		$nextStep = array();
		foreach ($categoryArray as $categoryObject)
		{
			$res = $NetWpProduct->createNewWooCategory($categoryObject);
			if ($res !== true && !empty($res))
				$nextStep[] = $res;
		}
		if (!empty($nextStep) && count($nextStep) < count($categoryArray))
			$this->createCatNexStep($nextStep);
		else
			return true;
	}

}