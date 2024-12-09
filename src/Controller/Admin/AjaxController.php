<?php

namespace NuveiCheckout\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @author Nuvei
 */

class AjaxController extends FrameworkBundleAdminController
{
    public function handleAjaxCall()
    {
        $data = [/* Your Ajax response data */];
        return new JsonResponse($data);
    }
    
}
