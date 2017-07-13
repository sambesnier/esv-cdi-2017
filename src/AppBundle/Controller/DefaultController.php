<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Payment;
use AppBundle\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\ORMException;
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
     * @Route("/{page}", name="homepage")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, $page =1)
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
            ->getAllPayments($page);

        $pagination = array(
            'page' => $page,
            'nbPages' => ceil(count($payments) / 15),
            'routeName' => 'homepage',
            'paramsRoute' => array()
        );

        // replace this example code with whatever you need
        return $this->render('AppBundle:Default:index.html.twig', [
            "fileForm" => $form->createView(),
            "payments" => $payments ?? [],
            "pagination" => $pagination
        ]);
    }

    /**
     * @Route(
     *     "/payments/{id}/{page}",
     *     name="payments_by_id"
     * )
     * @param User $user
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     * @internal param $id
     */
    public function paymentsByUserAction(User $user, $page = 1)
    {
        $payments = $this->getDoctrine()->getRepository('AppBundle:Payment')
            ->getPaymentsByUser($user, $page);

        $pagination = array(
            'page' => $page,
            'nbPages' => ceil(count($payments) / 15),
            'routeName' => 'payments_by_id',
            'id' => $user->getId(),
            'paramsRoute' => array()
        );

        // replace this example code with whatever you need
        return $this->render('AppBundle:Default:index.html.twig', [
            "payments" => $payments ?? [],
            "pagination" => $pagination
        ]);
    }

    /**
     * @Route(
     *     "/payments-by-nature/{id}/{page}",
     *     name="payments_by_nature"
     * )
     * @param Payment $payment
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function paymentsByNatureAction(Payment $payment, $page = 1)
    {
        $payments = $this->getDoctrine()->getRepository('AppBundle:Payment')
            ->getPaymentsByNature($payment, $page);

        $pagination = array(
            'page' => $page,
            'nbPages' => ceil(count($payments) / 15),
            'routeName' => 'payments_by_nature',
            'id' => $payment->getId(),
            'paramsRoute' => array()
        );

        // replace this example code with whatever you need
        return $this->render('AppBundle:Default:index.html.twig', [
            "payments" => $payments ?? [],
            "pagination" => $pagination
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

                    $date = $this->convertDate($columns[6]);

                    $payment
                        ->setPaymentDate(new \DateTime($date))
                        ->setAmount($columns[7])
                        ->setNature($columns[8])
                        ->setUser($user);
                    $user->addPayment($payment);
                } else {
                    $payment = new Payment();

                    $date = $this->convertDate($columns[5]);

                    $payment
                        ->setPaymentDate(new \DateTime($date))
                        ->setAmount($columns[6])
                        ->setNature($columns[7])
                        ->setUser($user);
                    $user->addPayment($payment);
                }

                try {
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($user);
                    $em->flush();
                } catch (ORMException $e) {
                } catch (UniqueConstraintViolationException $e) {
                }
            }
        }
    }

    private function convertDate($frDate)
    {
        $parts = explode('/', $frDate);

        return $parts[2].'-'.$parts[1].'-'.$parts[0];
    }
}
