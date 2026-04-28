<?php

namespace App\Command;

use App\Repository\GlobalStatRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:live:deactivate-products',
    description: 'Désactive automatiquement tous les produits en live le lendemain du live à 10h.',
)]
class DeactivateLiveProductsCommand extends Command
{
    public function __construct(
        private GlobalStatRepository $globalStatRepository,
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $globalStat = $this->globalStatRepository->getOrCreate();
        $nextLiveAt = $globalStat->getNextLiveAt();

        if (!$nextLiveAt) {
            $io->info('Aucun prochain live programmé, rien à faire.');
            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        // Seuil = lendemain du live à midi (heure Paris)
        $deactivationThreshold = $nextLiveAt
            ->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->setTime(12, 0, 0)
            ->modify('+1 day');

        if ($now < $deactivationThreshold) {
            $io->info(sprintf(
                'Il est trop tôt pour désactiver (seuil: %s, maintenant: %s).',
                $deactivationThreshold->format('d/m/Y H:i'),
                $now->format('d/m/Y H:i')
            ));
            return Command::SUCCESS;
        }

        // On désactive tous les produits encore "live"
        $liveProducts = $this->productRepository->findBy(['isLive' => true]);

        if (count($liveProducts) === 0) {
            $io->success('Aucun produit en live à désactiver.');
        } else {
            foreach ($liveProducts as $product) {
                $product->setIsLive(false);
                $this->entityManager->persist($product);
            }

            $this->entityManager->flush();
            $io->success(sprintf('Désactivation de %d produit(s) en live effectuée.', count($liveProducts)));
        }

        // On peut éventuellement réinitialiser nextLiveAt si on considère que ce live est passé
        $globalStat->setNextLiveAt(null);
        $this->entityManager->persist($globalStat);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
