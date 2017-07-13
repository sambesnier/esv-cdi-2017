<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Payment;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
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

        $serializer = new Serializer([new ObjectNormalizer()], [new CsvEncoder()]);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['file']->getData();
            $fileToCsv = $serializer->decode(file_get_contents($file), 'csv');

            $this->storeCSV($fileToCsv);
        }

        $payments = $this->getDoctrine()->getRepository('AppBundle:Payment')
            ->getAllPayments();

        // replace this example code with whatever you need
        return $this->render('AppBundle:Default:index.html.twig', [
            "fileForm" => $form->createView(),
            "payments" => $payments ?? []
        ]);
    }

    /**
     * Store the CSV content in database
     *
     * @param $fileToCsv
     */
    private function storeCSV($fileToCsv)
    {
        $pattern = '/\d{2}\/\d{2}\/\d{4}/';
        foreach ($fileToCsv as $line) {
            foreach ($line as $data) {
                $columns = explode(';', $data);
                $user = $this->getDoctrine()->getRepository('AppBundle:User')
                    ->findOneByEmail($columns[2]);
                if (empty($user)) {
                    $user = new User();
                    $user
                        ->setName($columns[0])
                        ->setFirstname($columns[1])
                        ->setEmail($columns[2]);
                }
                if (!empty($columns[3])) {
                    $address = new Address();
                    $address
                        ->setAddress($columns[3])
                        ->setPostcode($columns[4])
                        ->setCity($columns[5])
                        ->addUser($user);
                    $user->setAddress($address);
                }
                if (preg_match($pattern, $columns[6])) {
                    $payment = new Payment();
                    $paymentDate = \DateTime::createFromFormat('d/m/Y', $columns[6]);
                    $payment
                        ->setPaymentDate($paymentDate)
                        ->setAmount($columns[7])
                        ->setNature($columns[8])
                        ->setUser($user);
                    $user->addPayment($payment);
                } else {
                    $payment = new Payment();
                    $paymentDate = \DateTime::createFromFormat('d/m/Y', $columns[5]);
                    $payment
                        ->setPaymentDate($paymentDate)
                        ->setAmount($columns[6])
                        ->setNature($columns[7])
                        ->setUser($user);
                    $user->addPayment($payment);
                }

                $em = $this->getDoctrine()->getManager();
                $em->persist($user);
                $em->flush();
            }
        }
    }
}
