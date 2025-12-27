<?php

namespace App\Command;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audit:cleanup',
    description: 'Deletes activity logs older than 2 months.',
)]
class AuditCleanupCommand extends Command
{
    public function __construct(
        private ActivityLogRepository $logRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $cutoffDate = (new \DateTimeImmutable())->modify('-2 months');

        $io->info('Finding activity logs older than ' . $cutoffDate->format('d/m/Y H:i'));

        $count = $this->logRepository->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();

        if ($count === 0) {
            $io->success('No old logs to clean up.');
        } else {
            $io->success(sprintf('Successfully deleted %d old activity logs.', $count));
        }

        return Command::SUCCESS;
    }
}
