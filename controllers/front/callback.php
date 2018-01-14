<?php
include('baseaction.php');

class RaiwireCallbackModuleFrontController extends BaseAction
{
    public function postProcess()
    {
        $message = "";
        $responseCode = '400';
        $cart = null;
        if ($this->validateAction($message, $cart)) {
            $message = $this->processAction($cart, $responseCode);
        } else {
            $message = empty($message) ? "Unknown error" : $message;
            $this->createLogMessage($message, 3, $cart);
        }

        $header = "X-Raiwire-System: ". 'Prestashop/' . $_PS_VERSION_ . ' Module/0.1' . ' PHP/'. phpversion();;
        header($header, true, $responseCode);
        die($message);
    }
}
