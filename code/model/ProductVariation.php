<?php
/**
 * @todo How does this class work in relation to Product?
 *
 * @package ecommerce
 */
class ProductVariation extends DataObject {

	public static $db = array(
		'InternalItemID' => 'Varchar(30)',
		'Price' => 'Currency',
		'AllowPurchase' => 'Boolean',
		'Sort' => 'Int',
		'Description' => 'Varchar(255)'
	);

	public static $has_one = array(
		'Product' => 'Product',
		'Image' => 'ProductVariation_Image'
	);

	static $many_many = array(
		'AttributeValues' => 'ProductAttributeValue'
	);

	public static $casting = array(
		'Parent' => 'Product',
		'Title' => 'HTMLText',
		'Link' => 'Text',
		'AllowPuchaseText' => 'Text',
		'PurchasedTotal' => 'Int',
		'CalculatedPrice' => 'Currency'
	);

	public static $versioning = array(
		'Stage'
	);

	public static $extensions = array(
		"Versioned('Stage')",
		"Buyable"
	);

	public static $indexes = array(
		"Sort" => true
	);

	public static $defaults = array(
		"AllowPurchase" => 1
	);

	public static $field_labels = array(
		"Description" => "Title (optional)"
	);

	public static $summary_fields = array(
		'Product.Title' => 'Product',
		'Title' => 'Title',
		'Price' => 'Price',
		'AllowPuchaseText' => 'Buyable',
		'PurchasedTotal' => 'Purchased Total'
	);

	public static $searchable_fields = array(
		'Price' => array(
			'field' => 'NumericField',
			'title' => 'Price'
		),
		"Product.Title" => "PartialMatchFilter"
	);

	public static $default_sort = "\"Sort\" ASC, \"InternalItemID\" ASC, \"Price\" ASC";

	public static $singular_name = "Product Variation";
		function i18n_singular_name() { return _t("ProductVariation.PRODUCTVARIATION", "Product Variation");}

	public static $plural_name = "Product Variations";
		function i18n_plural_name() { return _t("ProductVariation.PRODUCTVARIATIONS", "Product Variations");}
		public static function get_plural_name(){
			$obj = Singleton("ProductVariation");
			return $obj->i18n_plural_name();
		}

	/**
 	 *@param
    **/
	protected static $title_style_option = array(
		"default" => array(
			"ShowType" => true,
			"BetweenTypeAndValue" => ": ",
			"BetweenVariations" => ", "
		)
	);
		public static function add_title_style_option($code, $showType, $betweenTypeAndValue, $betweenVariations) {
			self::$title_style_option[$code] = array(
				"ShowType" => $showType,
				"BetweenTypeAndValue" => $betweenTypeAndValue,
				"BetweenVariations" => $betweenVariations
			);
			self::set_current_style_option_code($code);
		}
		public static function remove_title_style_option($code) {unset(self::$title_style_option[$code]);}

	protected static $current_style_option_code = "default";
		public static function set_current_style_option_code($v) {self::$current_style_option_code = $v;}
		public static function get_current_style_option_code() {return self::$current_style_option_code;}

	public static function get_current_style_option_array() {
		return self::$title_style_option[self::get_current_style_option_code()];
	}

	function getCMSFields() {
		$product = $this->Product();
		$fields = new FieldSet(new TabSet('Root',
			new Tab('Main',
				new NumericField('Price'),
				new CheckboxField('AllowPurchase', _t("ProductVariation.ALLOWPURCHASE", 'Allow Purchase ?')),
				new TextField('InternalItemID', _t("ProductVariation.INTERNALITEMID", 'Internal Item ID')),
				new TextField('Description', _t("ProductVariation.DESCRIPTION", "Description (optional)")),
				new ImageField('Image')
			)
		));
		$types = $product->VariationAttributes();
		if($this->ID) {
			$purchased = $this->getPurchasedTotal();
			$values = $this->AttributeValues();
			foreach($types as $type) {
				$field = $type->getDropDownField();
				if($field) {
					$value = $values->find('TypeID', $type->ID);
					if($value) {
						$field->setValue($value->ID);
						if($purchased) {
							$field = $field->performReadonlyTransformation();
							$field->setName("Type{$type->ID}");
						}
					}
					else {
						if($purchased) {
							$field = new ReadonlyField("Type{$type->ID}", $type->Name, _t("ProductVariation.ALREADYPURCHASED", 'NOT SET (you can not select a value now because it has already been purchased).'));
						}
						else {
							$field->setEmptyString('');
						}
					}
				}
				else {
					$field = new ReadonlyField("Type{$type->ID}", $type->Name, _t("ProductVariation.NOVALUESTOSELECT", 'No values to select'));
				}
				$fields->addFieldToTab('Root.Attributes', $field);
			}
			$fields->addFieldToTab('Root.Orders',
				new ComplexTableField(
					$this,
					'OrderItems',
					'ProductVariation_OrderItem',
					array(
						'Order.ID' => '#',
						'Order.Created' => 'When',
						'Order.Member.Name' => 'Member',
						'Quantity' => 'Quantity',
						'Total' => 'Total'
					),
					new FieldSet(),
					"\"BuyableID\" = '$this->ID'",
					"\"Created\" DESC"
				)
			);
		}
		else {
			foreach($types as $type) {
				$field = $type->getDropDownField();
				$fields->addFieldToTab('Root.Attributes', $field);
			}
		}
		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	function getRequirementsForPopup() {
		$purchased = $this->getPurchasedTotal();
		if(! $this->ID || ! $purchased) {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			Requirements::javascript('ecommerce_product_variation/javascript/productvariation.js');
			Requirements::customScript("ProductVariation.set_url('createecommercevariations')", 'CreateEcommerceVariationsField_set_url');
			Requirements::customCSS('#ComplexTableField_Popup_AddForm input.loading {background: url("cms/images/network-save.gif") no-repeat scroll left center #FFFFFF; padding-left: 16px;}');
		}
	}

	function IsProductVariation() {
		return true;
	}

	function onAfterWrite() {
		parent::onAfterWrite();
		if(isset($_POST['ProductAttributes']) && is_array($_POST['ProductAttributes'])){
			$this->AttributeValues()->setByIDList(array_values($_POST['ProductAttributes']));
		}
		unset($_POST['ProductAttributes']);
	}

	function onBeforeDelete() {
		parent::onBeforeDelete();
		$this->AttributeValues()->removeAll();
	}

	function Link(){
		return $this->Product()->Link();
	}

	/**
	 * We use this function to make it more universal.
	 * For a buyable, a parent could refer to a ProductGroup OR a Product
	 * @return DataObject | Null
	 **/
	function Parent(){return $this->getParent();}
	function getParent(){
		return $this->Product();
	}

	function Title(){return $this->getTitle();}
	function getTitle($withSpan = false, $noProductTitle = false){
		$styleArray = self::get_current_style_option_array();
		$values = $this->AttributeValues();
		if($values->exists()){
			$labelvalues = array();
			if(count($values)) {
				foreach($values as $value){
					$v = '';
					if($withSpan) {
						$v = '<span>';
					}
					if($styleArray["ShowType"]) {
						$v .= $value->Type()->Label.$styleArray["BetweenTypeAndValue"];
					}
					$v .= $value->Value;
					if($withSpan) {
						$v .= '</span>';
					}
					$labelvalues[] = $v;
				}
			}
			$title = implode($styleArray["BetweenVariations"],$labelvalues);
		}
		else {
			$title = $this->InternalItemID;
		}
		if($this->Description) {
			if($withSpan) {
				$title .= "; <span class=\"extraDescription\">".$this->Description."</span>";
			}
			else {
				$title .= "; ".$this->Description;
			}
		}
		if($noProductTitle) {
			return $title;
		}
		else {
			if($withSpan) {
				$productTitle = '<span class="productTitle">'.$this->Product()->Title.'</span>';
			}
			else {
				$productTitle = $this->Product()->Title;
			}
		}
		return $productTitle. " ".$title;
	}


	function AllowPuchaseText() {return $this->getAllowPuchaseText();}
	function getAllowPuchaseText() {
		return $this->AllowPurchase ? 'Yes' : 'No';
	}

	function PurchasedTotal() {return $this->getPurchasedTotal();}
	function getPurchasedTotal() {
		return DB::query("SELECT COUNT(*) FROM \"OrderItem\" WHERE \"BuyableID\" = '$this->ID'")->value();
	}

	function CalculatedPrice() {return $this->getCalculatedPrice();}
	function getCalculatedPrice() {
		$price = $this->Price;
		$this->extend('updateCalculatedPrice',$price);
		return $price;
	}


	//this is used by TableListField to access attribute values.
	function AttributeProxy(){
		$do = new DataObject();
		if($this->AttributeValues()->exists()){
			foreach($this->AttributeValues() as $value){
				$do->{'Val'.$value->Type()->Name} = $value->Value;
			}
		}
		return $do;
	}

	function canDelete() {
		return $this->getPurchasedTotal() == 0;
	}


	//TODO: provide human-understandable reasons variation can't be purcahsed
	function canPurchase($member = null) {
		$allowpurchase = false;
		if(!$this->AllowPurchase) {
			return false;
		}
		if($product = $this->Product()) {
			$allowpurchase = $product->canPurchase($member);
		}
		$extended = $this->extendedCan('canPurchase', $member);
		if($allowpurchase && $extended !== null) {
			$allowpurchase = $extended;
		}
		return $allowpurchase;
	}

	function populateDefaults() {
		$this->AllowPurchase = 1;
	}

	function QuantityDecimals(){
		return 0;
	}


}

class ProductVariation_Image extends Image {

}

class ProductVariation_OrderItem extends Product_OrderItem {

	// ProductVariation Access Function
	public function ProductVariation($current = false) {
		//TO DO: the line below does not work because it does NOT get the right version
		return $this->Buyable(true);
		//THIS WORKS
		return DataObject::get_by_id("ProductVariation", $this->BuyableID);
	}


	/**
	 * Check if two order items are the same
	 * @param Object - $orderItem should be a ProductVariation_OrderItem;
	 * @return Boolean
	 **/
	function hasSameContent($orderItem) {
		$parentIsTheSame = parent::hasSameContent($orderItem);
		return $parentIsTheSame && $orderItem instanceof ProductVariation_OrderItem;
	}

	/**
	 * price per item
	 *@return Float
	 **/
	function UnitPrice($recalculate = false) {return $this->getUnitPrice($recalculate);}
	function getUnitPrice($recalculate = false) {
		$unitprice = 0;
		if($this->priceHasBeenFixed() && !$recalculate) {
			return parent::getUnitPrice($recalculate);
		}
		elseif($productVariation = $this->ProductVariation()){
			$unitprice = $productVariation->getCalculatedPrice();
			$this->extend('updateUnitPrice',$unitprice);
		}
		return $unitprice;
	}

	/**
	 *@decription: we return the product name here -
	 * leaving the Table Sub Title for the name of the variation
	 *@return String - title in cart.
	 **/
	public function TableTitle(){return $this->getTableTitle();}
	function getTableTitle() {
		$tabletitle = $this->ProductVariation()->Product()->Title;
		$this->extend('updateTableTitle',$tabletitle);
		return $tabletitle;
	}

	/**
	 *@decription: we return the product variation name here
	 * the Table Title will return the name of the Product.
	 *@return String - sub title in cart.
	 **/
	function TableSubTitle() {return $this->getTableSubTitle();}
	function getTableSubTitle() {
		$tablesubtitle = $this->ProductVariation()->getTitle(true, true);
		$this->extend('updateTableSubTitle',$tablesubtitle);
		return $tablesubtitle;
	}

	function onBeforeWrite() {
		parent::onBeforeWrite();
	}


	function requireDefaultRecords() {
		parent::requireDefaultRecords();
		// we must check for individual database types here because each deals with schema in a none standard way
		//can we use Table::has_field ???
		$db = DB::getConn();
		if($db->hasTable("Product_OrderItem")) {
			if( $db instanceof PostgreSQLDatabase ){
				$exist = DB::query("SELECT column_name FROM information_schema.columns WHERE table_name ='Product_OrderItem' AND column_name = 'ProductVariationVersion'")->numRecords();
			}
			else{
				// default is MySQL - broken for others, each database conn type supported must be checked for!
				$exist = DB::query("SHOW COLUMNS FROM \"Product_OrderItem\" LIKE 'ProductVariationVersion'")->numRecords();
			}
			if($exist > 0) {
				DB::query("
					UPDATE \"OrderItem\", \"ProductVariation_OrderItem\"
						SET \"OrderItem\".\"Version\" = \"ProductVariation_OrderItem\".\"ProductVariationVersion\"
					WHERE \"OrderItem\".\"ID\" = \"ProductVariation_OrderItem\".\"ID\"
				");
				DB::query("
					UPDATE \"OrderItem\", \"ProductVariation_OrderItem\"
						SET \"OrderItem\".\"BuyableID\" = \"ProductVariation_OrderItem\".\"ProductVariationID\"
					WHERE \"OrderItem\".\"ID\" = \"ProductVariation_OrderItem\".\"ID\"
				");
				DB::query("ALTER TABLE \"ProductVariation_OrderItem\" CHANGE COLUMN \"ProductVariationVersion\" \"_obsolete_ProductVariationVersion\" Integer(11)");
				DB::query("ALTER TABLE \"ProductVariation_OrderItem\" CHANGE COLUMN \"ProductVariationID\" \"_obsolete_ProductVariationID\" Integer(11)");
				DB::alteration_message('made ProductVariationVersion and ProductVariationID obsolete in ProductVariation_OrderItem', 'obsolete');
			}
		}
	}


}
