<?php

namespace NuveiCheckout\Controller\Front;

use PrestaShopBundle\Controller\Front\PrestaShopController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Nuvei
 */

class PaymentController extends PrestaShopController
{
    /**
     * Generating a link:
     * $url = $this->context->link->getModuleLink('nuvei', 'payment');
        // 'nuvei' is the module name, and 'payment' is the front controller (payment.php)
     */
    
    public function payment(Request $request): Response
    {
        // Your logic for handling payment actions
        // Access POST or GET data via $request->get() or $request->query->get()
        
//        return new Response('Payment processing logic here');
        
        // return template with variables if need to
        return $this->render(
            '@Modules/nuvei_checkout/views/templates/front/payment.html.twig',
            [
                'example_variable' => 'value',
            ]
        );
    }
    
    public function processDmn(Request $request)
    {
        
    }
    
}
