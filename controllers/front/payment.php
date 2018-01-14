<?php
class RaiwirePaymentModuleFrontController extends ModuleFrontController
{
    
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'raiwire') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'raiwire'));
        }

        $paymentRequest = $this->module->createPaymentWindowRequest($cart);

        if (!isset($paymentRequest)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $paymentData = array('paymentRequest' => $paymentRequest,
                             'cancelUrl' => $paymentRequest["raiwire_cancelurl"]
                            );

        $this->context->smarty->assign($paymentData);

        $this->setTemplate('module:raiwire/views/templates/front/payment.tpl');
    }
}
