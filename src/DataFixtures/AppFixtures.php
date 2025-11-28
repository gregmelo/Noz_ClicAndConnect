<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // --- Utilisateurs ---
        $users = [];
        
        // 1. Dev (Le plus haut)
        $dev = new User();
        $dev->setEmail('dev@noz.fr');
        $dev->setFirstName('Dev');
        $dev->setLastName('Team');
        $dev->setRoles(['ROLE_DEVELOPER']); // Mapped to ROLE_DEV in user request, assuming ROLE_DEVELOPER from security.yaml
        $dev->setPassword($this->passwordHasher->hashPassword($dev, 'password'));
        $manager->persist($dev);
        $users['dev'] = $dev;

        // 2. Directeur (Super Admin)
        $directeur = new User();
        $directeur->setEmail('directeur@noz.fr');
        $directeur->setFirstName('Directeur');
        $directeur->setLastName('Noz');
        $directeur->setRoles(['ROLE_SUPER_ADMIN']);
        $directeur->setPassword($this->passwordHasher->hashPassword($directeur, 'password'));
        $manager->persist($directeur);
        $users['directeur'] = $directeur;

        // 3. Adjoint (Admin)
        $adjoint = new User();
        $adjoint->setEmail('adjoint@noz.fr');
        $adjoint->setFirstName('Adjoint');
        $adjoint->setLastName('Noz');
        $adjoint->setRoles(['ROLE_ADMIN']);
        $adjoint->setPassword($this->passwordHasher->hashPassword($adjoint, 'password'));
        $manager->persist($adjoint);
        $users['adjoint'] = $adjoint;

        // 4. Employé
        $employee = new User();
        $employee->setEmail('employe@noz.fr');
        $employee->setFirstName('Jean');
        $employee->setLastName('Employé');
        $employee->setRoles(['ROLE_EMPLOYEE']);
        $employee->setPassword($this->passwordHasher->hashPassword($employee, 'password'));
        $manager->persist($employee);
        $users['employee'] = $employee;

        // 5. Client
        $client = new User();
        $client->setEmail('client@noz.fr');
        $client->setFirstName('Sophie');
        $client->setLastName('Client');
        $client->setRoles(['ROLE_CLIENT']); // Explicitly setting ROLE_CLIENT though it's default
        $client->setPassword($this->passwordHasher->hashPassword($client, 'password'));
        $manager->persist($client);
        $users['client'] = $client;

        // --- Produits ---
        $products = [];
        $images = ['product_1.jpg', 'product_2.jpg', 'product_3.jpg', 'product_4.jpg'];

        for ($i = 0; $i < 20; $i++) {
            $product = new Product();
            $product->setName($faker->words(3, true));
            $product->setDescription($faker->paragraph(2));
            
            $price = $faker->randomFloat(2, 5, 100);
            $product->setPrice($price);

            if ($faker->boolean(30)) {
                $product->setOriginalPrice($price * $faker->randomFloat(2, 1.1, 1.5));
            }

            $product->setStock($faker->numberBetween(0, 50));
            $product->setImageFilename($faker->randomElement($images));
            
            $manager->persist($product);
            $products[] = $product;
        }

        // --- Réservations (pour le client) ---
        // 1. Réservation ACTIVE
        $resActive = new Reservation();
        $resActive->setUser($client);
        $resActive->setReference('RES-' . strtoupper(uniqid()));
        $resActive->setStatus('ACTIVE');
        $resActive->setReservedAt(new \DateTimeImmutable('-1 hour'));
        $resActive->setExpiresAt((new \DateTimeImmutable('-1 hour'))->modify('+48 hours'));
        
        $item1 = new ReservationItem();
        $item1->setProduct($products[0]);
        $item1->setQuantity(2);
        $resActive->addReservationItem($item1);
        
        $manager->persist($resActive);

        // 2. Réservation READY
        $resReady = new Reservation();
        $resReady->setUser($client);
        $resReady->setReference('RES-' . strtoupper(uniqid()));
        $resReady->setStatus('READY');
        $resReady->setReservedAt(new \DateTimeImmutable('-24 hours'));
        $resReady->setExpiresAt((new \DateTimeImmutable('-24 hours'))->modify('+48 hours'));
        
        $item2 = new ReservationItem();
        $item2->setProduct($products[1]);
        $item2->setQuantity(1);
        $resReady->addReservationItem($item2);

        $manager->persist($resReady);

        // 3. Réservation COLLECTED
        $resCollected = new Reservation();
        $resCollected->setUser($client);
        $resCollected->setReference('RES-' . strtoupper(uniqid()));
        $resCollected->setStatus('COLLECTED');
        $resCollected->setReservedAt(new \DateTimeImmutable('-5 days'));
        $resCollected->setExpiresAt((new \DateTimeImmutable('-5 days'))->modify('+48 hours'));
        
        $item3 = new ReservationItem();
        $item3->setProduct($products[2]);
        $item3->setQuantity(3);
        $resCollected->addReservationItem($item3);

        $manager->persist($resCollected);

        $manager->flush();
    }
}
