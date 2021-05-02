<?php

namespace App\Controller;

use App\Entity\OffreEmploi;
use App\Entity\Category;
use App\Entity\User;
use App\Entity\DemandeRecrutement;
use App\Form\OffreEmploiType;
use App\Repository\OffreEmploiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class OffreEmploiController extends AbstractController
{
    /**
     * @Route("/addjob", name="addjob")
     */
    public function addjob(Request $request)
    {
        $offre = new OffreEmploi();
        $form = $this->createForm(OffreEmploiType::class, $offre);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $offre->setIdRecruteur(null);
                $offre->setIdCandidat(null);
                $offre->setDateDebut(new \DateTime('now'));
                $em->persist($offre);
                $em->flush();
                return $this->redirectToRoute('joblist');
            } else {
                return $this->render('offre_emploi/postjob.html.twig', [
                    'form' => $form->createView(), 'message' => 'Check your fields !'
                ]);
            }
        }
        return $this->render('offre_emploi/postjob.html.twig', [
            'form' => $form->createView(), 'message' => ''
        ]);
    }


    /**
     * @Route("/seeapp/{id}", name="seeapp")
     */
    public function seeapp($id, Request $request, PaginatorInterface $pag)
    {
        $r = $this->getDoctrine()->getRepository(User::class);
        $app = $this->getDoctrine()->getRepository(DemandeRecrutement::class)->findBy(['offre' => $id]);
        $off = $this->getDoctrine()->getRepository(OffreEmploi::class)->findBy(['id' => $id]);
        $arr = array();
        foreach ($app as $value) {
            $user = $r->findBy(['id' => $value->getCandidat()]);
            array_push($arr, array($app, $user));
        }
        //dump($arr[0][1]);

        $apps = $pag->paginate($app, $request->query->getInt('page', 1), 4);

        return $this->render('offre_emploi/manageapp.html.twig', [
            'list' => $apps, 'arr' => $arr, 'offre' => $off
        ]);
    }

    /**
     * 
     * @Route("/treatapp/{id}", name="treatapp")
     */
    public function treatapp($id, Request $request, PaginatorInterface $pag)
    {
        $this->getDoctrine()->getRepository(DemandeRecrutement::class)->treat($id);
        return $this->seeapp($this->getUser()->getId(),  $request,  $pag);
    }

    /**
     * @Route("/modify/{id}", name="modify")
     */
    public function modjob(Request $request, $id)
    {
        $job = $this->getDoctrine()->getRepository(OffreEmploi::class)->find($id);
        $form = $this->createForm(OffreEmploiType::class, $job);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($job);
                $em->flush();
                return $this->redirectToRoute('joblist');
            } else {
                return $this->render('offre_emploi/postjob.html.twig', [
                    'form' => $form->createView(), 'message' => 'Check your fields !'
                ]);
            }
        }
        return $this->render('offre_emploi/postjob.html.twig', [
            'form' => $form->createView(), 'message' => ''
        ]);
    }

    /**
     * @Route("/joblist", name="joblist")
     */
    public function readjob(Request $request, PaginatorInterface $pag)
    {
        $r = $this->getDoctrine()->getRepository(OffreEmploi::class);
        $donnes = $r->findBy([], ['date_debut' => 'DESC']);

        $count = [];

        // On "démonte" les données pour les séparer tel qu'attendu par ChartJS
        foreach ($donnes as $job) {
            $count[] = count($job->getApplies());
        }

        $jobs = $pag->paginate($donnes, $request->query->getInt('page', 1), 4);

        return $this->render('offre_emploi/managejob.html.twig', [
            'list' => $jobs, 'count' => $count
        ]);
    }

    /**
     * @Route("/browsejob", name="browsejob")
     */
    public function browsejob(Request $request, PaginatorInterface $pag)
    {
        $r = $this->getDoctrine()->getRepository(OffreEmploi::class);
        $filtre = $request->get("searchaj");

        $donnes = $r->getdonn($filtre);

        $jobs = $pag->paginate($donnes, $request->query->getInt('page', 1), 4);
        $nb = $r->countj($filtre);

        if ($request->get('ajax')) {
            return new JsonResponse([
                'content' => $this->renderView('offre_emploi/content.html.twig', [
                    'list' => $jobs, 'nb' => $nb
                ])
            ]);
        }

        return $this->render('offre_emploi/browsejob.html.twig', [
            'list' => $jobs, 'nb' => $nb
        ]);
    }

    /**
     * @Route("/deljob/{id}", name="deljob")
     */
    public function deljob($id)
    {
        $em = $this->getDoctrine()->getManager();
        $job = $em->getRepository(OffreEmploi::class)->find($id);
        $em->remove($job);
        $em->flush();
        return $this->redirectToRoute('joblist');
    }

    /**
     * @Route("/search", name="search")
     */
    public function searchjob(Request $request, PaginatorInterface $pag)
    {
        $title = $request->request->get('titre');
        $location = $request->request->get('location');
        $secteur = $request->request->get('secteur');

        $r = $this->getDoctrine()->getRepository(OffreEmploi::class);
        $donnes = $r->search($title, $location, $secteur);
        $nb = $r->countsearch($title, $location, $secteur);
        $jobs = $pag->paginate($donnes, $request->query->getInt('page', 1), 4);

        return $this->render('offre_emploi/browsejob.html.twig', [
            'list' => $jobs, 'nb' => $nb
        ]);
    }


    /**
     * @Route("/pdf/{id}", name="pdf")
     */
    public function pofjob($id)
    {
        $job = $this->getDoctrine()->getManager()->getRepository(OffreEmploi::class)->findBy(['id' => $id]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('offre_emploi/pdf.html.twig', [
            'list' => $job
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("PDFPffre.pdf", [
            "Attachment" => true
        ]);

        return $this->redirectToRoute('browsejob');
    }

    /**
     * @Route("/pdfAll", name="pdfAll")
     */
    public function pdfjobs()
    {
        $job = $this->getDoctrine()->getManager()->getRepository(OffreEmploi::class)->findAll();

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('offre_emploi/pdf.html.twig', [
            'list' => $job
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("PDFPffres.pdf", [
            "Attachment" => true
        ]);

        return $this->redirectToRoute('browsejob');
    }
    /**
     * @Route("/details", name="details")
     */
    public function details()
    {
        $r = $this->getDoctrine()->getManager();
        $categs = $r->getRepository(Category::class)->findAll();

        $categNom = [];
        $categColor = [];
        $categCount = [];

        // On "démonte" les données pour les séparer tel qu'attendu par ChartJS
        foreach ($categs as $categ) {
            $categNom[] = $categ->getTitre();
            $categColor[] = $categ->getCouleur();
            $categCount[] = count($categ->getOffreemplois());
        }
        // On va chercher le nombre d'annonces publiées par date
        $annRepo = $r->getRepository(DemandeRecrutement::class);
        $annonces = $annRepo->countByDate();

        $dates = [];
        $annoncesCount = [];

        // On "démonte" les données pour les séparer tel qu'attendu par ChartJS
        foreach ($annonces as $annonce) {
            $dates[] = $annonce['dateAnnonces'];
            $annoncesCount[] = $annonce['count'];
        }

        return $this->render('offre_emploi/jobdetails.html.twig', [
            'categNom' => json_encode($categNom),
            'categColor' => json_encode($categColor),
            'categCount' => json_encode($categCount),
            'dates' => json_encode($dates),
            'annoncesCount' => json_encode($annoncesCount),
        ]);
    }


    /**
     * @Route("/listofferjson", name="listofferjson")
     */
    public function listofferjson(OffreEmploiRepository $repo, SerializerInterface $ser)
    {

        $offers = $repo->findAll();
        $serializer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()]);
        //relation //circular  referance
        $data = $serializer->normalize($offers, null, array('attributes' => array(
            'id', 'titre', 'poste', 'description', 'date_debut',
            'date_expiration', 'maxSalary', 'minSalary', 'location', 'file', 'email', 'categorie' => ['id'], 'applies' => ['id']
        )));
        //$data = $serializer->normalize($offers, 'json');
        return new JsonResponse($data);
    }

    /**
     * @Route("/addofferjson", name="addofferjson")
     */
    public function addofferjson(Request $req, SerializerInterface $ser)
    {
        $ser = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()]);
        $man = $this->getDoctrine()->getManager();
        $content = $req->getContent();
        $data = $ser->deserialize($content, null, array('attributes' => array(
            'id', 'titre', 'poste', 'description', 'date_debut',
            'date_expiration', 'maxSalary', 'minSalary', 'location', 'file', 'email', 'categorie' => ['id'], 'applies' => ['id']
        )));
        $data->setIdRecruteur(null);
        $data->setIdCandidat(null);
        $data->setDateDebut(new \DateTime('now'));
        $data = $ser->normalize($data, null, array('attributes' => array(
            'id', 'titre', 'poste', 'description', 'date_debut',
            'date_expiration', 'maxSalary', 'minSalary', 'location', 'file', 'email', 'categorie' => ['id'], 'applies' => ['id']
        )));
        $man->persist($data);
        $man->flush();
        return new Response("success !");
    }

    /**
     * @Route("/updateofferjson", name="updateofferjson")
     */
    public function updateofferjson(Request $req, SerializerInterface $ser)
    {
        $man = $this->getDoctrine()->getManager();
        $content = $req->getContent();
        $var = json_decode($req->getContent());
        $job = $this->getDoctrine()->getRepository(OffreEmploi::class)->find($var->{'id'});
        $job->setDateexpiration($var->{'date_expiration'});
        $categ = $this->getDoctrine()->getRepository(Category::class)->find($var->{'categorie_id'});
        $job->setCategory($categ);
        $job->setTitle($var->{'id'});
        $job->setTitle($var->{'id'});
        $man->persist($job);
        $man->flush();
    }

    /**
     * @Route("/deleteofferjson", name="deleteofferjson")
     */
    public function deleteofferjson(Request $req, SerializerInterface $ser)
    {
        $em = $this->getDoctrine()->getManager();
        $content = $req->getContent();
        $data = $ser->deserialize($content, OffreEmploi::class, 'json');
        $job = $this->getDoctrine()->getRepository(OffreEmploi::class)->find($data->getId());
        $em->remove($job);
        $em->flush();
    }

    /**
     * 
     * @Route("/treatappjson", name="treatappjson")
     */
    public function treatappjson(Request $request, SerializerInterface $ser)
    {
        $content = $request->getContent();
        $data = $ser->deserialize($content, DemandeRecrutement::class, 'json');
        $this->getDoctrine()->getRepository(DemandeRecrutement::class)->treat($data->getId());
    }

    /**
     * @Route("/seeappjson", name="seeappjson")
     */
    public function seeseeappjsonapp(Request $request, SerializerInterface $ser)
    {
        $content = $request->getContent();
        $data = $ser->deserialize($content, OffreEmploi::class, 'json');
        $r = $this->getDoctrine()->getRepository(User::class);
        $app = $this->getDoctrine()->getRepository(DemandeRecrutement::class)->findBy(['offre' => $data->getId()]);
        $off = $this->getDoctrine()->getRepository(OffreEmploi::class)->findBy(['id' => $data->getId()]);
        $arr = array();
        foreach ($app as $value) {
            $user = $r->findBy(['id' => $value->getCandidat()]);
            array_push($arr, array($app, $off, $user));
        }
        $json = $ser->serialize($arr, 'json', ['groups' => 'demande', 'groups' => 'offers', 'groups' => 'users']);
        dump($json);
        die;
    }
}
