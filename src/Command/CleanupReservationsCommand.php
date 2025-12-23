<?php

namespace App\Command;

use App\Entity\Reservation;
use App\Repository\GlobalStatRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-reservations',
    description: 'Archives and deletes reservations older than 1 week.',
)]
class CleanupReservationsCommand extends Command
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private GlobalStatRepository $globalStatRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Date cutoff: 1 week ago
        $cutoffDate = (new \DateTimeImmutable())->modify('-1 week');

        $io->info('Finding reservations older than ' . $cutoffDate->format('d/m/Y H:i'));

        // Find Candidates: statuses COMMITTED (COLLECTED, CANCELLED, EXPIRED) and older than cutoff
        // using reservedAt or expiresAt? reservedAt is safer for 'created' date.
        // Let's use reservedAt for consistency.
        $reservations = $this->reservationRepository->createQueryBuilder('r')
            ->where('r.status IN (:statuses)')
            ->andWhere('r.reservedAt < :cutoff')
            ->setParameter('statuses', ['COLLECTED', 'CANCELLED', 'EXPIRED'])
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $count = count($reservations);
        if ($count === 0) {
            $io->success('No old reservations to clean up.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Processing %d reservations...', $count));

        $globalStat = $this->globalStatRepository->getOrCreate();
        
        $revenueToAdd = 0.0;
        $collectedCountToAdd = 0;
        $expiredCountToAdd = 0;

        foreach ($reservations as $reservation) {
            /** @var Reservation $reservation */
            
            if ($reservation->getStatus() === 'COLLECTED') {
                $collectedCountToAdd++;
                // Calculate revenue for this reservation
                foreach ($reservation->getReservationItems() as $item) {
                    $itemPrice = $item->getPrice(); 
                    $revenueToAdd += ($itemPrice * $item->getQuantity());
                }
            } else {
                $expiredCountToAdd++;
            }

            // Delete
            $this->entityManager->remove($reservation);
        }

        // Update Stats
        $globalStat->setTotalRevenue($globalStat->getTotalRevenue() + $revenueToAdd);
        $globalStat->setTotalCollectedCount($globalStat->getTotalCollectedCount() + $collectedCountToAdd);
        $globalStat->setTotalExpiredCount($globalStat->getTotalExpiredCount() + $expiredCountToAdd);

        $this->entityManager->persist($globalStat);
        $this->entityManager->flush();

        $io->success(sprintf('Archived %d reservations. Revenue aggregated: %.2f €.', $count, $revenueToAdd));

        return Command::SUCCESS;
    }
}
