<?php
include('baseaction.php');
class RaiwireAcceptModuleFrontController extends BaseAction
{
    
    public function postProcess()
    {
        $message = "";
        $responseCode = '400';
        $cart = null;
        if ($this->validateAction($message, $cart)) {
            $message = $this->processAction($cart, $responseCode);
            if ($responseCode != 200) {
                $this->handleError($message, $cart);
            }
            $this->redirectToAccept($cart);
        } else {
            $message = empty($message) ? $this->l("Unknown error") : $message;
            $this->handleError($message, $cart);
        }
    }

    private function redirectToAccept($cart)
    {
        Tools::redirectLink(__PS_BASE_URI__. 'order-confirmation.php?key='. $cart->secure_key. '&id_cart='. (int)$cart->id. '&id_module='. (int)$this->module->id. '&id_order='. (int)Order::getOrderByCartId($cart->id));
    }

    private function handleError($message, $cart)
    {
        $this->createLogMessage($message, 3, $cart);
        Context::getContext()->smarty->assign('paymenterror', $message);
        
        $this->setTemplate('module:raiwire/views/templates/front/paymenterror.tpl');

    }
}
