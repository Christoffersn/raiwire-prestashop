<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Raiwire extends PaymentModule
{

    public function __construct()
    {
        $this->name = 'raiwire';
        $this->version = '0.1';
        $this->author = 'Christoffer Samuel Nielsen';

        $this->tab = 'payments_gateways';

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->controllers = array('accept', 'callback', 'payment');
        $this->is_eu_compatible = 1;    

        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = 'Raiwire';
        $this->description = $this->l('Accept payment in Raiblocks (XRB) using raiwire.com');

    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('paymentOptions')

        ) {
            return false;
        }

        if (!$this->createTransactionTable()) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    private function createTransactionTable()
    {
        $table_name = _DB_PREFIX_ . 'raiwire_transactions';

        $columns = array(
            'id_order' => 'int(10) unsigned NOT NULL',
            'id_cart' => 'int(10) unsigned NOT NULL',
            'transaction_id' => 'char(36) unsigned NOT NULL',
            'originalcurrency' => 'int(4) unsigned NOT NULL DEFAULT 0',
            'originalamount' => 'decimal(15,2) unsigned NOT NULL',
            'xrbamount' => 'int(24) unsigned NOT NULL',
            'date_add' => 'datetime NOT NULL'
        );

        $query = 'CREATE TABLE IF NOT EXISTS `' . $table_name . '` (';

        foreach ($columns as $column_name => $options) {
            $query .= '`' . $column_name . '` ' . $options . ', ';
        }

        $query .= ' PRIMARY KEY (`transaction_id`) )';

        if (!Db::getInstance()->Execute($query)) {
            return false;
        }

        return true;
    }


    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            $storeid = (string)Tools::getValue('RAIWIRE_STOREID');
            if (!$storeid  || empty($storeid)) {
                $output .= $this->displayError($this->l('Store ID is required.'));
            } else {
                Configuration::updateValue('RAIWIRE_STOREID', Tools::getValue("RAIWIRE_STOREID"));
                Configuration::updateValue('RAIWIRE_KEY', Tools::getValue("RAIWIRE_KEY"));
                
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->displayForm();
    }

    private function displayForm()
    {

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => 'Settings'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Merchant number',
                    'name' => 'RAIWIRE_STOREID',
                    'size' => 40,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => 'Secret',
                    'name' => 'RAIWIRE_KEY',
                    'size' => 40,
                    'required' => false
                )
            ),
            'submit' => array(
                'title' => 'Save',
                'class' => 'button'
            )
        );

        $helper = new HelperForm();


        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->title = $this->displayName . " v" . $this->version;
        $helper->show_toolbar = true;        
        $helper->toolbar_scroll = true;      
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $helper->fields_value['RAIWIRE_STOREID'] = Configuration::get('RAIWIRE_STOREID');
       
        $helper->fields_value['RAIWIRE_KEY'] = Configuration::get('RAIWIRE_KEY');
        

        $html =   '<div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-7 col-lg-7 ">'
                           .$helper->generateForm($fields_form)
                    .'</div>
                   </div>';
        return $html;
    }

    public function addDbTransaction($id_order, $id_cart, $transaction_id, $originalcurrency, $originalamount, $xrbamount)
    {

        $query = 'INSERT INTO ' . _DB_PREFIX_ . 'raiwire_transactions
                (id_order, id_cart, transaction_id, originalcurrency, originalamount, xrbamount,date_add)
                VALUES
                (' . pSQL($id_order) . ', ' . pSQL($id_cart) . ', \'' . pSQL($transaction_id) . '\', \'' . pSQL($originalcurrency) . '\', ' . pSQL($originalamount) . ', ' . pSQL($xrbamount) . ', NOW() )';
        
        return $this->executeDbQuery($query);
    }

    public function addDbOrderIdToTransaction($transaction_id, $id_order)
    {
        if (!$transaction_id || !$id_order) {
            return false;
        }

        $query = 'UPDATE '  . _DB_PREFIX_ . 'raiwire_transactions SET id_order="' . pSQL($id_order) . '" WHERE transaction_id="'.pSQL($transaction_id).'"';
        return $this->executeDbQuery($query);
    }

    private function getDbTransactionsByOrderId($id_order) {

        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'raiwire_transactions WHERE id_order = ' . pSQL($id_order);
        return $this->getDbTransactions($query);
    }

    private function getDbTransactionsByCartId($id_cart) {

        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'raiwire_transactions WHERE id_cart = ' . pSQL($id_cart);
        return $this->getDbTransactions($query);
    }

    private function getDbTransactions($query)
    {
        $transactions = Db::getInstance()->executeS($query);

        if (!isset($transactions) || count($transactions) === 0 || !isset($transactions[0]["transaction_id"])) {
            return false;
        }

        return $transactions[0];
    }


    private function executeDbQuery($query)
    {
        try {
            if (!Db::getInstance()->Execute($query)) {
                return false;
            }
        }
        catch (Exception $e) {
            return false;
        }

        return true;
    }



    public function hookPaymentOptions($params)
    {
  
        $cart = $params['cart'];

        $paymentInfoData = array('storeid' => Configuration::get('RAIWIRE_STOREID'),
                                );

        $this->context->smarty->assign($paymentInfoData);

        $callToActionText = "Raiwire - Pay with Raiblocks";

        $PaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $PaymentOption->setCallToActionText($callToActionText)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));

        $paymentOptions = array();
        $paymentOptions[] = $PaymentOption;

        return $paymentOptions;
    }

    public function hookPaymentReturn($params)
    {


        $order = $params['order'];

        $transaction = $this->getDbTransactionsByOrderId($order->id);

        if(!$transaction) {
            $transaction = $this->getDbTransactionsByCartId($order->id_cart);
            if(!$transaction || !$transaction["transaction_id"]) {
                return "";
            }
        }

        $transactionId = $transaction["transaction_id"];

        $this->context->smarty->assign('raiwire_completed_paymentText', $this->l('You completed your payment.'));

        return $this->display(__FILE__, 'views/templates/front/payment_return.tpl');
    }
    
    public function hookDisplayHeader()
    {
        if ($this->context->controller != null) {
            $this->context->controller->addCSS($this->_path.'views/css/raiwireFront.css', 'all');
        }
    }

    public function createPaymentWindowRequest()
    {
        $parameters = array();
        $parameters["raiwire_storeid"] = Configuration::get('RAIWIRE_STOREID');
        $parameters["raiwire_cms"] = "prestashop";
        
        $currency = $this->context->currency->iso_code;
        $amount = $this->context->cart->getOrderTotal();
        
        $parameters["raiwire_currency"]  = $currency;
        $parameters["raiwire_amount"]  = $amount;
        $parameters["raiwire_orderid"]  = $this->context->cart->id;
        $parameters["raiwire_accepturl"] = $this->context->link->getModuleLink($this->name, 'accept', array(), true);
        $parameters["raiwire_cancelurl"] = $this->context->link->getPageLink('order', true, null, "step=3");
        $parameters["raiwire_callbackurl"] = $this->context->link->getModuleLink($this->name, 'callback', array(), true);

        $hash = "";
        foreach ($parameters as $value) {
            $hash .= $value;
        }
        $salt = Configuration::get('RAIWIRE_KEY');
        $parameters["raiwire_hash"] = md5($hash . $salt);

        return $parameters;
    }

    
}
