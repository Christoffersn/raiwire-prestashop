<?php

abstract class BaseAction extends ModuleFrontController
{
    
    protected function validateAction(&$message, &$cart)
    {
        if (!Tools::getIsset("txnid")) {
            $message = "No GET(txnid) was supplied to the system!";
            return false;
        }

        $id_cart = null;


        if (!Tools::getIsset("orderid")) {
            $message = "No GET(orderid) was supplied to the system!";
            return false;
        }
        $id_cart = Tools::getValue("orderid");


        $cart = new Cart($id_cart);

        if (!isset($cart->id)) {
            $message =  "Please provide a valid orderid or cartid";
            return false;
        }

        $storeSecret = Configuration::get('RAIWIRE_KEY');
        if (!empty($storeSecret)) {
            $var = ""; 
            
            $var .= Tools::getValue("txnid");
            $var .= Tools::getValue("amount");
            $var .= $id_cart;

            $storeHash = md5($var . $storeSecret);
            

            if (strtoupper($storeHash) != strtoupper(Tools::getValue("hash"))) {
                $message = "Hash validation failed - Please check your secret";
                return false;
            }
        }

        return true;
    }


    protected function processAction($cart, &$responseCode)
    {
        $message = "";
        try {
            if (!$cart->orderExists() ) {
                $id_cart = $cart->id;
                $transaction_Id = Tools::getValue("txnid");
                $ps_currency = new Currency($cart->id_currency);
                $originalcurrency = $ps_currency->iso_code;
                $originalamount = $cart->getOrderTotal();
                $xrbamount = Tools::getValue('amount');
                $paymentMethod = "Raiwire";


                if ($this->module->addDbTransaction(
                    0,
                    $id_cart,
                    $transaction_Id,
                    $originalcurrency,
                    $originalamount,
                    $xrbamount
                    )) {
                    
                    try {
                        $this->module->validateOrder(
                            (int)$id_cart,
                            Configuration::get('PS_OS_PAYMENT'),
                            $originalamount,
                            $paymentMethod,
                            null,
                            null,
                            null,
                            false,
                            $cart->secure_key );
                    } catch (Exception $ex) {
                        die($ex->getMessage());
                        $message = "Prestashop threw an exception on validateOrder: " . $ex->getMessage();
                        $responseCode = 500;

                        return $message;
                    }
                    

                    $id_order = Order::getOrderByCartId($id_cart);
                    $this->module->addDbOrderIdToTransaction($transaction_Id, $id_order);
                    $order = new Order($id_order);

                    $payment = $order->getOrderPayments();
                    $payment[0]->transaction_id = $transaction_Id;
                    $payment[0]->amount = $originalamount;
                    $payment[0]->payment_method = $paymentMethod;

                    
                    $payment[0]->save();
                    $message = "Order created";
                    $responseCode = 200;
                } else {
                    $message = "Order is beeing created or have been created by another process";
                    $responseCode = 200;
                }
            } else {
                $message = "Order was already Created";
                $responseCode = 200;
            }
        } catch (Exception $e) {
            die($e->getMessage());
            $responseCode = 500;
            $message = "Process order failed with an exception: " .$e->getMessage();
        }
        return $message;
    }


    protected function createLogMessage($message, $severity = 3, $cart = null)
    {
        $result = "";
        if (isset($cart)) {
            $invoiceAddress = new Address((int)$cart->id_address_invoice);
            $customer = new Customer((int)$cart->id_customer);
            $personString = "Name: {$invoiceAddress->firstname}{$invoiceAddress->lastname} Mail: {$customer->email} - ";
            $result = $personString;
        }
        $result .= "An payment error occured: " . $message;
        
         PrestaShopLogger::addLog($result, $severity);
    }
}
