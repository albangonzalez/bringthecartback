<?php
/**
 * 1997-2016 Quadra Informatique
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is available
 * through the world-wide-web at this URL: http://www.opensource.org/licenses/OSL-3.0
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to modules@quadra-informatique.fr so we can send you a copy immediately.
 *
 * @author    Alban Gonzalez <alban.gonzalez@traxlead.com>
 * @copyright 1997-2016 Traxlead
 * @license   http://www.opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

class AdminBringTheCartBackController extends ModuleAdminController
{

	private $tpl_path;

	private $sent_emails_number = 0;

	public function __construct()
	{
		$this->module = 'bringthecartback';
		$this->context = Context::getContext();
		$this->bootstrap = true;
		$this->className = 'Cart';
		$this->explicitSelect = true;

		// Database query
		$this->table = 'cart';
		$this->_select = 'CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) `customer`, a.id_cart total, cr.reminder_sent, IF(cr.reminder_sent, 1, 0) badge_success';
		$this->_join = 'LEFT JOIN '._DB_PREFIX_.'cart_product cp ON a.id_cart = cp.id_cart
		LEFT JOIN '._DB_PREFIX_.'customer c ON (c.id_customer = a.id_customer)
		LEFT JOIN '._DB_PREFIX_.'orders o ON (o.id_cart = a.id_cart)
		LEFT JOIN '._DB_PREFIX_.'cart_reminder_bcb cr ON a.id_cart = cr.id_cart';
		$this->_where = 'AND a.id_customer!=0 AND cp.id_product IS NOT NULL AND o.id_order IS NULL';
		$this->_group = 'GROUP BY a.id_cart';
		$this->_orderWay = 'DESC';

		$this->addRowAction('view');
		// $this->addRowAction('delete');

		$this->allow_export = true;

		$this->fields_list = array(
			'id_cart' => array(
				'title' => $this->l('ID'),
				'align' => 'text-center',
				'class' => 'fixed-width-xs'
			),
			'customer' => array(
				'title' => $this->l('Customer'),
				'filter_key' => 'c!lastname'
			),
			'total' => array(
				'title' => $this->l('Total'),
				'callback' => 'getOrderTotalUsingTaxCalculationMethod',
				'orderby' => false,
				'search' => false,
				'align' => 'text-right',
			),
			'date_add' => array(
				'title' => $this->l('Date'),
				'align' => 'text-center',
				'type' => 'datetime',
				'filter_key' => 'a!date_add'
			),
			'reminder_sent' => array(
				'title' => $this->l('Reminder Date'),
				'align' => 'text-center',
				'type'  => 'datetime',
				'filter_key' => 'cr!reminder_sent',
				'badge_success' => true
			)
		);

		$this->setOptions();

		$this->tpl_path =  str_replace('controllers/admin', 'views/templates/admin/controllers/', __DIR__);
		$this->base_tpl_view = 'view.tpl';

		parent::__construct();
	}
	public function setOptions()
	{
		$this->fields_options = array(
			'settings' => array(
				'title' =>	$this->l('Settings'),
				'fields' =>	array(
					'BCB_REMINDER_LIMIT' => array(
						'title' => $this->l('Reminder Limit'),
						'desc' => $this->l('Send reminder for carts having less than x days.'),
						'hint' => $this->l('Number of days'),
						'validation' => 'isInt',
						'cast' => 'intval',
						'type' => 'text',
						'identifier' => 'BCB_REMINDER_LIMIT'
					),
					'BCB_FALSE_ABANDONNED_CART_LIMIT' => array(
						'title' => $this->l('False Abandonned Cart Limit'),
						'desc' => $this->l('Avoid sending multiple e-mails for false abandonned carts. Sometimes PrestaShop considers a cart is abandonned even if the customer bought the same cart with a different ID. This allows to check x hours before and after if the client made a purchase.'),
						'validation' => 'isUnsignedId', 
						'type' => 'select', 
						'cast' => 'intval', 
						'identifier' => 'false_time_limit', 
						'list' => array(
							array(
								'false_time_limit' => 3,
								'name' => $this->l('3 Hours')
							 ),
							array(
								'false_time_limit' => 6,
								'name' => $this->l('6 Hours')
							 ),
							array(
								'false_time_limit' => 12,
								'name' => $this->l('12 Hours')
							 ),
							array(
								'false_time_limit' => 24,
								'name' => $this->l('24 Hours')
							 ),
							array(
								'false_time_limit' => 48,
								'name' => $this->l('48 Hours')
							 ),
							array(
								'false_time_limit' => 72,
								'name' => $this->l('72 Hours')
							 ),
						)
					)
				),
				'submit' => array('title' => $this->l('Save'))
			)
		);

		return true;
	}
	public function renderPanelAction()
	{
		$lastDaysNumber = Configuration::get('BCB_REMINDER_LIMIT');
		$recoverableCartsNumber = count($this->getRecoverableCartsFromLastDays($lastDaysNumber));

		$actionUrl = 'index.php?controller=AdminBringTheCartBack&action=sendmailrecovercart&token='.$this->token;

		$action = Tools::getValue('action');
		if(!empty($action) && $action == 'sendmailrecovercart')
		{
			$this->context->smarty->assign('sent_emails', $this->sent_emails_number);
		}

		$this->context->smarty->assign('send_mail_action_url', $actionUrl);
		$this->context->smarty->assign('last_days_number', $lastDaysNumber);
		$this->context->smarty->assign('recoverable_carts_number', $recoverableCartsNumber);
		$view = $this->context->smarty->fetch($this->tpl_path . 'cart_reminder_action.tpl');


		return $view;
	}
	/**
	 * Assign smarty variables for all default views, list and form, then call other init functions
	 */
	public function initContent()
	{
		if (!$this->viewAccess())
		{
			$this->errors[] = Tools::displayError('You do not have permission to view this.');
			return;
		}

		$action = Tools::getValue('action');
		if(!empty($action) && $action == 'sendmailrecovercart')
		{
			$this->ajaxProcessSendMailRecoverCart();
			
		}
		$this->getLanguages();
		$this->initToolbar();
		$this->initTabModuleList();
		$this->initPageHeaderToolbar();


		if ($this->display == 'edit' || $this->display == 'add')
		{
			if (!$this->loadObject(true))
				return;

			$this->content .= $this->renderForm();
		}
		elseif ($this->display == 'view')
		{
			// Some controllers use the view action without an object
			if ($this->className)
				$this->loadObject(true);
			$this->content .= $this->renderView();
		}
		elseif ($this->display == 'details')
		{
			$this->content .= $this->renderDetails();
		}
		elseif (!$this->ajax)
		{
			$this->content .= $this->renderModulesList();
			$this->content .= $this->renderKpis();
			$this->content .= $this->renderOptions();
			$this->content .= $this->renderPanelAction();
			$this->content .= $this->renderList();

			// if we have to display the required fields form
			if ($this->required_database)
				$this->content .= $this->displayRequiredFields();
		}

		$this->context->smarty->assign(array(
			'content' => $this->content,
			'lite_display' => $this->lite_display,
			'url_post' => self::$currentIndex.'&token='.$this->token,
			'show_page_header_toolbar' => $this->show_page_header_toolbar,
			'page_header_toolbar_title' => $this->page_header_toolbar_title,
			'title' => $this->page_header_toolbar_title,
			'toolbar_btn' => $this->page_header_toolbar_btn,
			'page_header_toolbar_btn' => $this->page_header_toolbar_btn
		));
	}

	public function initToolbar()
	{
		parent::initToolbar();
		unset($this->toolbar_btn['new']);
	}
	// public function postProcess()
	// {
	// 	$this->ajaxProcessSendMailValidateOrder();
	// }
	public function renderView()
	{
		if (!($cart = $this->loadObject(true)))
			return;
		$customer = new Customer($cart->id_customer);
		$currency = new Currency($cart->id_currency);
		$this->context->cart = $cart;
		$this->context->currency = $currency;
		$this->context->customer = $customer;
		$this->toolbar_title = sprintf($this->l('Cart #%06d'), $this->context->cart->id);
		$products = $cart->getProducts();
		$customized_datas = Product::getAllCustomizedDatas((int)$cart->id);
		Product::addCustomizationPrice($products, $customized_datas);
		$summary = $cart->getSummaryDetails();

		/* Display order information */
		$id_order = (int)Order::getOrderByCartId($cart->id);
		$order = new Order($id_order);
		if (Validate::isLoadedObject($order))
		{
			$tax_calculation_method = $order->getTaxCalculationMethod();
			$id_shop = (int)$order->id_shop;
		}
		else
		{
			$id_shop = (int)$cart->id_shop;
			$tax_calculation_method = Group::getPriceDisplayMethod(Group::getCurrent()->id);
		}
		
		if ($tax_calculation_method == PS_TAX_EXC)
		{
			$total_products = $summary['total_products'];
			$total_discounts = $summary['total_discounts_tax_exc'];
			$total_wrapping = $summary['total_wrapping_tax_exc'];
			$total_price = $summary['total_price_without_tax'];
			$total_shipping = $summary['total_shipping_tax_exc'];
		}
		else
		{
			$total_products = $summary['total_products_wt'];
			$total_discounts = $summary['total_discounts'];
			$total_wrapping = $summary['total_wrapping'];
			$total_price = $summary['total_price'];
			$total_shipping = $summary['total_shipping'];
		}
		foreach ($products as $k => &$product)
		{
			if ($tax_calculation_method == PS_TAX_EXC)
			{
				$product['product_price'] = $product['price'];
				$product['product_total'] = $product['total'];
			}
			else
			{
				$product['product_price'] = $product['price_wt'];
				$product['product_total'] = $product['total_wt'];
			}
			$image = array();
			if (isset($product['id_product_attribute']) && (int)$product['id_product_attribute'])
				$image = Db::getInstance()->getRow('SELECT id_image
																FROM '._DB_PREFIX_.'product_attribute_image
																WHERE id_product_attribute = '.(int)$product['id_product_attribute']);
			if (!isset($image['id_image']))
				$image = Db::getInstance()->getRow('SELECT id_image
																FROM '._DB_PREFIX_.'image
																WHERE id_product = '.(int)$product['id_product'].' AND cover = 1');

			$product_obj = new Product($product['id_product']);
			$product['qty_in_stock'] = StockAvailable::getQuantityAvailableByProduct($product['id_product'], isset($product['id_product_attribute']) ? $product['id_product_attribute'] : null, (int)$id_shop);

			$image_product = new Image($image['id_image']);
			$product['image'] = (isset($image['id_image']) ? ImageManager::thumbnail(_PS_IMG_DIR_.'p/'.$image_product->getExistingImgPath().'.jpg', 'product_mini_'.(int)$product['id_product'].(isset($product['id_product_attribute']) ? '_'.(int)$product['id_product_attribute'] : '').'.jpg', 45, 'jpg') : '--');
		}

		$this->tpl_view_vars = array(
			'products' => $products,
			'discounts' => $cart->getCartRules(),
			'order' => $order,
			'cart' => $cart,
			'currency' => $currency,
			'customer' => $customer,
			'customer_stats' => $customer->getStats(),
			'total_products' => $total_products,
			'total_discounts' => $total_discounts,
			'total_wrapping' => $total_wrapping,
			'total_price' => $total_price,
			'total_shipping' => $total_shipping,
			'customized_datas' => $customized_datas
		);

		$helper = new HelperView($this);
		$this->setHelperDisplay($helper);
		
		// Get template used on native CartsController
		$helper->base_folder = _PS_BO_ALL_THEMES_DIR_ 
			. 'default/template/controllers/carts/'
			. $helper->base_folder;

		$helper->tpl_vars = $this->tpl_view_vars;
		
		$view = $helper->generateView();

		return $view;
	}
	public function ajaxProcessSendMailRecoverCart()
	{
		$recoverableCarts = $this->getRecoverableCartsFromLastDays(Configuration::get('BCB_REMINDER_LIMIT'));
		$customerIds = array();

		if($recoverableCarts)
		{
			$now = new DateTime();
			foreach ($recoverableCarts as $key => $cart)
			{
				if(!$this->hasCustomerOrderedDuringInterval($cart, Configuration::get('BCB_FALSE_ABANDONNED_CART_LIMIT')))
				{
					if(!in_array($cart['id_customer'], $customerIds))
					{
						$this->sendMail($cart['id_cart']);
						$customerIds[] = $cart['id_customer'];
					}

					Db::getInstance()->insert('cart_reminder_bcb', array(
							'id_cart' => $cart['id_cart'],
							'reminder_sent' => $now->format('Y-m-d H:i:s'),
						)
					);
				}
			}
			$this->sent_emails_number = count($customerIds);
		}
		// if ($this->tabAccess['edit'] === '1')
		// {
			// $cart = new Cart((int)Tools::getValue('id_cart'));

		// }
	}
	public function sendMail($cartId)
	{
		$cart = new Cart($cartId);
		if (Validate::isLoadedObject($cart))
		{
			$customer = new Customer((int)$cart->id_customer);
			if (Validate::isLoadedObject($customer))
			{
				$mailVars = array(
					'{order_link}' => Context::getContext()->link->getPageLink('order', false, (int)$cart->id_lang, 'step=3&recover_cart='.(int)$cart->id.'&token_cart='.md5(_COOKIE_KEY_.'recover_cart_'.(int)$cart->id)),
					'{firstname}' => $customer->firstname,
					'{lastname}' => $customer->lastname
				);
				if (Mail::Send((int)$cart->id_lang, 'backoffice_order', Mail::l('Process the payment of your order', (int)$cart->id_lang), $mailVars, 'alban.gonzalez@traxlead.com', //$customer->email,
						$customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, $cart->id_shop))
				return true;
			}
		}
	}
	public static function getOrderTotalUsingTaxCalculationMethod($id_cart)
	{
		$context = Context::getContext();
		$context->cart = new Cart($id_cart);
		$context->currency = new Currency((int)$context->cart->id_currency);
		$context->customer = new Customer((int)$context->cart->id_customer);
		return Cart::getTotalCart($id_cart, true, Cart::BOTH_WITHOUT_SHIPPING);
	}
	public function getRecoverableCartsFromLastDays($days)
	{
		$currentDateTime = new DateTime();
		$dateInterval = new DateInterval('P' . $days . 'D');

		$lastDateTime = clone $currentDateTime;
		$lastDateTime->sub($dateInterval);

		$statement = 'SELECT a.*, cr.reminder_sent AS reminder_sent, a.id_cart total, cr.reminder_sent
			FROM `'._DB_PREFIX_.'cart` a
			LEFT JOIN '._DB_PREFIX_.'cart_product cp ON a.id_cart = cp.id_cart
			LEFT JOIN '._DB_PREFIX_.'orders o ON (o.id_cart = a.id_cart)
			LEFT JOIN '._DB_PREFIX_.'cart_reminder_bcb cr ON a.id_cart = cr.id_cart
			WHERE a.id_customer!=0 AND cp.id_product IS NOT NULL AND o.id_order IS NULL AND cr.reminder_sent IS NULL AND a.date_add > \'' . $lastDateTime->format('Y-m-d H:i:s') . '\'
			GROUP BY a.id_cart
			ORDER BY a.`id_cart` DESC';

		if ($results = Db::getInstance()->executeS($statement))
			return $results;
		else
			return false;
	}
	public function hasCustomerOrderedDuringInterval($cart, $interval)
	{
		$cartDateTime = new DateTime($cart['date_add']);
		$dateInterval = new DateInterval('PT' . $interval. 'H');
		
		$beforeDateTime = clone $cartDateTime;
		$beforeDateTime->sub($dateInterval);

		$afterDateTime = clone $cartDateTime;
		$afterDateTime->add($dateInterval);

		$statement = 'SELECT * FROM '._DB_PREFIX_.'orders o
			WHERE id_customer = ' . $cart['id_customer'] . ' AND ( `date_add` BETWEEN \'' . $beforeDateTime->format('Y-m-d H:i:s') . '\' AND \'' . $afterDateTime->format('Y-m-d H:i:s') . '\')';
		if ($results = Db::getInstance()->executeS($statement))
		{
			if(count($results)>0)
				return true;
			else
				return false;
		}
		return false;
	}
}