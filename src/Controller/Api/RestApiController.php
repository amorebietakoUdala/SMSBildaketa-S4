<?php

namespace App\Controller\Api;

use App\Entity\Label;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use App\Entity\Contact;
use Symfony\Component\HttpFoundation\Response;

class RestApiController extends AbstractFOSRestController
{
    /**
     * Retrieves an Labels resource.
     *
     * @QueryParam(name="name", description="The pattern of the Label to lookup")
     */
    public function getLabelsAction(ParamFetcherInterface $paramFetcher): View
    {
        $query = $paramFetcher->get('name');
        $repo = $this->getDoctrine()->getRepository(Label::class);
        $labels = $repo->findLabelsThatContain($query);

        return View::create(['labels' => $labels], Response::HTTP_OK);
    }

    /**
     * @QueryParam(name="contact", description="Contact to remove label from")
     * @QueryParam(name="label", description="Label to remove from de contact")
     */
    public function deleteLabelRemoveAction(ParamFetcherInterface $paramFetcher): View
    {
        $contact_id = $paramFetcher->get('contact');
        $label_id = $paramFetcher->get('label');
        $em = $this->getDoctrine()->getManager();
        $contact_repo = $this->getDoctrine()->getRepository(Contact::class);
        $label_repo = $this->getDoctrine()->getRepository(Label::class);
        $contact = $contact_repo->find($contact_id);
        $label = $label_repo->find($label_id);
        $contact->removeLabel($label);
        $em->persist($contact);
        $em->flush();

        return View::create([], Response::HTTP_NO_CONTENT);
    }
}
