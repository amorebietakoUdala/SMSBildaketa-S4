<?php

namespace App\AmorebietakoUdala\SMSServiceBundle\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="sms_getCredit", methods={"GET"})
     */
    public function indexAction(SmsSender $sms)
    {
        $credit = $sms->getCredit();

        return $this->render('@SMSServiceBundle/default/index.html.twig', [
            'credit' => $credit,
        ]);
    }
}
