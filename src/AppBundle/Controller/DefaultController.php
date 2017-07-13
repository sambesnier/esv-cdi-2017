<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $defaultData = array('message' => 'Formulaire d\'importation');

        $form = $this->createFormBuilder($defaultData)
            ->add(
                'file',
                FileType::class,
                [
                    'label' => 'Fichier CSV'
                ])
            ->add(
                'submit',
                SubmitType::class,
                [
                    'label' => 'Envoyer'
                ]
            )
            ->getForm();

        $form->handleRequest($request);

        // replace this example code with whatever you need
        return $this->render('AppBundle:Default:index.html.twig', [
            "fileForm" => $form->createView()
        ]);
    }
}
