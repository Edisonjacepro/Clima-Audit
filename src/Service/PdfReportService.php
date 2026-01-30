<?php

namespace App\Service;

use App\Entity\Audit;
use App\Entity\PdfReport;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfReportService
{
    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $entityManager,
        private string $reportDir
    ) {
    }

    public function generate(Audit $audit): PdfReport
    {
        $result = $audit->getResult();
        if ($result === null) {
            throw new \RuntimeException('Resultat indisponible.');
        }

        $report = $audit->getPdfReport() ?? new PdfReport();
        $report->setAudit($audit);
        $report->setStatus(PdfReport::STATUS_PENDING);

        $this->ensureReportDir();

        $html = $this->twig->render('pdf/report.html.twig', [
            'audit' => $audit,
            'result' => $result,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $fileName = sprintf('audit_%d_%s.pdf', $audit->getId(), (new \DateTimeImmutable())->format('Ymd_His'));
        $path = rtrim($this->reportDir, '/\\').DIRECTORY_SEPARATOR.$fileName;

        file_put_contents($path, $dompdf->output());

        $report->setStatus(PdfReport::STATUS_READY);
        $report->setPathOrKey($path);
        $report->setGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    public function getOrGenerate(Audit $audit): PdfReport
    {
        $report = $audit->getPdfReport();
        if ($report !== null && $report->getStatus() === PdfReport::STATUS_READY) {
            $path = $report->getPathOrKey();
            if ($path !== null && is_file($path)) {
                return $report;
            }
        }

        return $this->generate($audit);
    }

    private function ensureReportDir(): void
    {
        if (!is_dir($this->reportDir)) {
            mkdir($this->reportDir, 0775, true);
        }
    }
}
