<?php

namespace App\Controller;

use App\Entity\Audit;
use App\Form\AuditType;
use App\Service\AuditOrchestrator;
use App\Service\GeocodingException;
use App\Service\GeocodingServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Annotation\Route;

class AuditController extends AbstractController
{
    #[Route('/audit/new', name: 'audit_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        GeocodingServiceInterface $geocodingService,
        AuditOrchestrator $auditOrchestrator
    ): Response {
        $audit = new Audit();
        $form = $this->createForm(AuditType::class, $audit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $geocoded = $geocodingService->geocode($audit->getAddress());
                $audit->setLat($geocoded->lat);
                $audit->setLng($geocoded->lng);
                $audit->setAddress($geocoded->formattedAddress);
            } catch (GeocodingException $exception) {
                $form->addError(new FormError($exception->getMessage()));
                if ($this->getParameter('kernel.debug')) {
                    $form->addError(new FormError('Diagnostic: verifiez la connectivite sortante et le User-Agent Nominatim.'));
                }
                return $this->render('audit/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $audit->setStatus(Audit::STATUS_PAID);
            $entityManager->persist($audit);
            $entityManager->flush();

            $auditOrchestrator->compute($audit);

            return $this->redirectToRoute('audit_result', ['id' => $audit->getId()]);
        }

        return $this->render('audit/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/audit/{id}/result', name: 'audit_result')]
    public function result(Audit $audit): Response
    {
        return $this->render('audit/result.html.twig', [
            'audit' => $audit,
            'result' => $audit->getResult(),
        ]);
    }

    #[Route('/audit/{id}/hazard/{hazard}', name: 'audit_hazard')]
    public function hazard(Audit $audit, string $hazard): Response
    {
        $result = $audit->getResult();
        if ($result === null) {
            throw $this->createNotFoundException('Resultat indisponible.');
        }

        $scores = $result->getScoresJson();
        $levels = $result->getLevelsJson();
        $explanations = $result->getExplanationsJson();

        if (!array_key_exists($hazard, $scores)) {
            throw $this->createNotFoundException('Alea inconnu.');
        }

        return $this->render('audit/hazard.html.twig', [
            'audit' => $audit,
            'hazard' => $hazard,
            'score' => $scores[$hazard],
            'level' => $levels[$hazard] ?? null,
            'explanation' => $explanations[$hazard] ?? null,
        ]);
    }
}
