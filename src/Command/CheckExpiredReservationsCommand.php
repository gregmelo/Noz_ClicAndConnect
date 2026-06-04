<?php

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\EmailNotificationService;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:check-expired-reservations',
    description: 'Checks for expired reservations, updates status, restores stock, and adds strikes.',
)]
class CheckExpiredReservationsCommand extends Command
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $entityManager,
        private EmailNotificationService $emailService,
        private PushNotificationService $pushService,
        private UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

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
            // 1. Mettre à jour le statut
            $reservation->setStatus('EXPIRED');

            // 2. Remettre en stock
            foreach ($reservation->getReservationItems() as $item) {
                $product = $item->getProduct();
                $product->setStock($product->getStock() + $item->getQuantity());
                $this->entityManager->persist($product);
            }

            // 3. Ajouter un strike
            $user = $reservation->getUser();
            $user->setStrikes($user->getStrikes() + 1);

            if ($user->getStrikes() >= 3) {
                $user->setBanExpiresAt((new \DateTimeImmutable())->modify('+7 days'));
                $io->note(sprintf('User %s has been banned due to excessive strikes.', $user->getEmail()));
            }

            $this->entityManager->persist($user);
            $this->entityManager->persist($reservation);

            // 4. Envoyer l'avertissement email
            try {
                $this->emailService->sendExpirationWarning($reservation);
            } catch (\Exception $e) {
                $io->warning('Email échec pour ' . $user->getEmail() . ' : ' . $e->getMessage());
            }

            // 5. Envoyer la notification push
            try {
                $url = $this->urlGenerator->generate('app_my_reservations', [], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->pushService->sendToUser(
                    $user,
                    '⚠️ Réservation expirée',
                    'Votre réservation ' . $reservation->getReference() . ' a expiré. Un avertissement a été ajouté à votre compte.',
                    $url
                );
            } catch (\Exception $e) {
                $io->warning('Push échec pour ' . $user->getEmail() . ' : ' . $e->getMessage());
            }
        }

        $this->entityManager->flush();

        try {
            $this->pushService->flush();
        } catch (\Exception $e) {
            $io->warning('Push flush échec : ' . $e->getMessage());
        }

        $io->success(sprintf('Processed %d expired reservations.', $count));

        return Command::SUCCESS;
    }
}