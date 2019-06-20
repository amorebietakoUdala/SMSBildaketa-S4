<?php

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Entity\Contact;
use App\Entity\Label;
use App\Form\ContactImportType;
use App\Form\ContactType;
use DateTime;
use Exception;
use League\Csv\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}")
 */
class ContactController extends AbstractController
{
    /**
     * @Route("/contacts/import", name="contact_import")
     */
    public function importAction(Request $request)
    {
        $form = $this->createForm(ContactImportType::class, null);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $already_existing_contacts = [];
            $repeated_contacts = [];
            $contacts_without_repeated = [];
            try {
                $file = $form['file']->getData();
                $records = $this->__readRecordsFromFile($file);
                $repo = $em->getRepository(Contact::class);
                foreach ($records as $record) {
                    $contactDTO = new ContactDTO();
                    $contactDTO->extractFromArray($record);
                    $contact = $repo->findOneBy(['telephone' => $contactDTO->getTelephone()]);
                    if (null === $contact) {
                        /* Telephone not found */
                        $contact = new Contact();
                        $contactDTO->fill($contact);
                    } else {
                        $already_existing_contacts[] = $contact;
                    }
                    if (!array_key_exists($contact->getTelephone(), $contacts_without_repeated)) {
                        $contacts_without_repeated[$contact->getTelephone()] = $contact;
                    } else {
                        $repeated_contacts[$contact->getTelephone()] = $contact;
                    }
                }
                $labels = $form['labels']->getData();
                $this->__assignLabelsToContacts(array_values($contacts_without_repeated), $labels);
                $em->flush();
                $this->addFlash('success', 'File successfully processed');
                $message = '';
                if (count($already_existing_contacts) > 0) {
                    $this->addFlash('warning', 'There are already existing contacts. the label will be applyied but the contact information has not be updated.');
                }
                if (count($repeated_contacts) > 0) {
                    $this->addFlash('warning', 'There are repeated contacts.');
                }
            } catch (Exception $e) {
                $this->addFlash('error', 'there was an error procesing file %message%');
                $message = $e->getMessage();
            }
            $this->__moveProcessedFile($file);

            return $this->render('contact/upload.html.twig', [
                'form' => $form->createView(),
                'message' => $message,
                'already_existing_contacts' => $already_existing_contacts,
                'repeated_contacts' => $repeated_contacts,
                'contacts_without_repeated' => $contacts_without_repeated,
        ]);
        }

        return $this->render('contact/upload.html.twig', [
            'form' => $form->createView(),
            'message' => null,
    ]);
    }

    /**
     * @Route("/contacts", name="contact_list")
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();
        $contacts = $em->getRepository(Contact::class)->findAll();

        return $this->render('contact/list.html.twig', [
            'contacts' => $contacts,
        ]);
    }

    /**
     * @Route("/contact/new", name="contact_new")
     */
    public function newAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(ContactType::class, new ContactDTO());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /* @var $data ContactDTO */
            $data = $form->getData();
            $exists = $em->getRepository(Contact::class)->findOneBy([
                'telephone' => $data->getTelephone(),
            ]);
            if ($exists) {
                $this->addFlash('error', 'Duplicate contact');

                return $this->render('/contact/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            $labels = $data->getLabels();
            $labels = $this->__removeLabelDuplicates($labels);
            $contact = new Contact();
            $data->fill($contact);
            $em->persist($contact);
            $em->flush();
            $this->addFlash('success', 'Contact saved');

            return $this->redirectToRoute('contact_list');
        }

        return $this->render('contact/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/contact/{contact}/edit", name="contact_edit")
     */
    public function editAction(Request $request, Contact $contact)
    {
        $em = $this->getDoctrine()->getManager();
        $contactDTO = new ContactDTO($contact);

        $form = $this->createForm(ContactType::class, $contactDTO, [
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /* @var $data ContactDTO */
            $data = $form->getData();
            $labels = $data->getLabels();
            $this->__removeCollectionDuplicates($labels, $this->__removeLabelDuplicates($labels));
            $data->fill($contact);
            $em->persist($contact);
            $em->flush();
            $this->addFlash('success', 'Contact saved');

            return $this->redirectToRoute('contact_list');
        }

        return $this->render('contact/edit.html.twig', [
            'form' => $form->createView(),
            'readonly' => false,
            'new' => false,
        ]);
    }

    /**
     * @Route("/contact/{contact}", name="contact_show")
     */
    public function showAction(Contact $contact)
    {
        $contactDTO = new ContactDTO($contact);

        $form = $this->createForm(ContactType::class, $contactDTO, [
        ]);

        return $this->render('contact/edit.html.twig', [
            'form' => $form->createView(),
            'readonly' => true,
            'new' => false,
        ]);
    }

    /**
     * @Route("/contact/{contact}/delete", name="contact_delete")
     */
    public function deleteAction(Contact $contact)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($contact);
        $em->flush();
        $this->addFlash('success', 'Contact deleted');

        return $this->redirectToRoute('contact_list');
    }

    /**
     * Return the labels array without duplicates.
     *
     * @var
     *
     * @return array $uniqueArray
     */
    private function __removeLabelDuplicates($labels)
    {
        $uniqueArray = [];
        foreach ($labels as $label) {
            if (!in_array($label->getName(), $uniqueArray)) {
                $uniqueArray[] = $label->getName();
            }
        }

        return $uniqueArray;
    }

    private function __removeCollectionDuplicates($labels, $uniques)
    {
        $em = $this->getDoctrine()->getManager();
        $labels->clear();
        foreach ($uniques as $unique) {
            $label = $em->getRepository(Label::class)->findOneBy(['name' => $unique]);
            if (null === $label) {
                $label = new Label();
                $label->setName($unique);
                $labels->add($label);
            } else {
                $labels->add($label);
            }
        }

        return $labels;
    }

    private function __readRecordsFromFile($file)
    {
        $reader = Reader::createFromPath($file, 'r');
        $reader->setDelimiter(';');
//                $reader->setHeaderOffset(0);
        $records = $reader->getRecords();

        return $records;
    }

    private function __moveProcessedFile($file)
    {
        $date = new DateTime();
        $dateStr = $date->format('Ymdhis');
        $filename = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file->getClientOriginalName());
        $directory = $this->getParameter('uploads_directory');
        $extension = $file->guessExtension();
        if (!$extension) {
            // extension cannot be guessed
            $extension = 'bin';
        }
        $file->move($directory, $dateStr.'-'.$filename.'.'.$extension);
    }

    private function __assignLabelsToContacts($contacts, $labels)
    {
        $em = $this->getDoctrine()->getManager();
        foreach ($contacts as $contact) {
            foreach ($labels as $label) {
                $label2 = $em->getRepository(Label::class)->findOneBy(['name' => $label->getName()]);
                if (null === $label2) {
                    $contact->addLabel($label);
                } else {
                    $contact->addLabel($label2);
                }
            }
            $em->persist($contact);
        }
    }
}
