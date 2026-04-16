<?php

namespace App\Command;

use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-expired-reservations',
    description: 'Checks for expired reservations, updates status, restores stock, and adds strikes.',
)]
class CheckExpiredReservationsCommand extends Command
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        // Find reservations that are READY and have expired
        $expiredReservations = $this->reservationRepository->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('status', 'READY')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = count($expiredReservations);
        $io->info(sprintf('Found %d expired reservations.', $count));

        foreach ($expiredReservations as $reservation) {
            // 1. Update Status
            $reservation->setStatus('EXPIRED');

            // 2. Restore Stock
            foreach ($reservation->getReservationItems() as $item) {
                $product = $item->getProduct();
                $product->setStock($product->getStock() + $item->getQuantity());
                $this->entityManager->persist($product);
            }

            // 3. Add Strike to User
            $user = $reservation->getUser();
            $user->setStrikes($user->getStrikes() + 1);
            
            // Check for Ban (e.g., if strikes >= 3)
            if ($user->getStrikes() >= 3) {
                // Ban for 7 days
                $user->setBanExpiresAt((new \DateTimeImmutable())->modify('+7 days'));
                $io->note(sprintf('User %s has been banned due to excessive strikes.', $user->getEmail()));
            }

            $this->entityManager->persist($user);
            $this->entityManager->persist($reservation);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Processed %d expired reservations.', $count));

        return Command::SUCCESS;
    }
}
