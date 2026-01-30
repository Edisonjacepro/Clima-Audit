<?php

namespace App\Controller;

use App\Entity\Audit;
use App\Form\AuditType;
use App\Service\GeocodingException;
use App\Service\GeocodingServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Annotation\Route;
use App\Message\ComputeAuditMessage;

class AuditController extends AbstractController
{
    #[Route('/audit/new', name: 'audit_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        GeocodingServiceInterface $geocodingService,
        MessageBusInterface $messageBus
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
                    $form->addError(new FormError('Diagnostic: verifiez la connectivite sortante vers data.geopf.fr et api-adresse.data.gouv.fr.'));
                }
                return $this->render('audit/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $audit->setStatus(Audit::STATUS_PAID);
            $entityManager->persist($audit);
            $entityManager->flush();

            $messageBus->dispatch(new ComputeAuditMessage($audit->getId()));

            return $this->redirectToRoute('audit_result', ['id' => $audit->getId()]);
        }

        $response = $this->render('audit/new.html.twig', [
            'form' => $form->createView(),
        ]);
        $response->setPrivate();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(0);

        return $response;
    }

    #[Route('/audit/{id}/result', name: 'audit_result')]
    public function result(Audit $audit): Response
    {
        $response = $this->render('audit/result.html.twig', [
            'audit' => $audit,
            'result' => $audit->getResult(),
            'isComputing' => $audit->getResult() === null || $audit->getStatus() !== Audit::STATUS_COMPUTED,
        ]);
        $response->setPrivate();
        $response->setMaxAge(30);
        $response->setSharedMaxAge(0);

        return $response;
    }

    #[Route('/audit/{id}/status', name: 'audit_status')]
    public function status(Audit $audit): JsonResponse
    {
        $result = $audit->getResult();
        $payload = [
            'status' => $audit->getStatus(),
            'computed' => $result !== null && $audit->getStatus() === Audit::STATUS_COMPUTED,
        ];

        return new JsonResponse($payload, headers: [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
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

        $response = $this->render('audit/hazard.html.twig', [
            'audit' => $audit,
            'hazard' => $hazard,
            'score' => $scores[$hazard],
            'level' => $levels[$hazard] ?? null,
            'explanation' => $explanations[$hazard] ?? null,
        ]);
        $response->setPrivate();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(0);

        return $response;
    }
}
