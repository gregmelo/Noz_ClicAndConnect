<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditController extends AbstractController
{
    #[Route('', name: 'app_admin_audit_index')]
    public function index(ActivityLogRepository $logRepository): Response
    {
        try {
            $logs = $logRepository->findLatest(150);
        } catch (\Exception $e) {
            // Probably table activity_log doesn't exist yet
            return $this->redirectToRoute('app_admin_audit_sync_db');
        }

        return $this->render('admin/audit/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/sync-db', name: 'app_admin_audit_sync_db')]
    public function syncDb(EntityManagerInterface $entityManager): Response
    {
        $schemaTool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        
        try {
            $schemaTool->updateSchema($classes);
            $this->addFlash('success', 'La table activity_log a été créée/mise à jour avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_audit_index');
    }

    #[Route('/purge', name: 'app_admin_audit_purge')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function purgeLogs(EntityManagerInterface $entityManager, ActivityLogRepository $logRepository): Response
    {
        $count = $logRepository->createQueryBuilder('l')
            ->delete()
            ->getQuery()
            ->execute();

        $this->addFlash('success', sprintf('%d logs d\'audit ont été supprimés.', $count));
        
        return $this->redirectToRoute('app_admin_audit_index');
    }
}
