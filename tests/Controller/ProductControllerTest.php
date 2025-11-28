<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    public function testIndexRedirectsAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product');

        // Should redirect to login
        $this->assertResponseRedirects('/login');
    }

    // To test authenticated access, we would need to load fixtures or mock the user.
    // Example (commented out until we have fixtures/test user):
    /*
    public function testIndexAccessForEmployee(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('employee@noz.fr');

        $client->loginUser($testUser);
        $client->request('GET', '/product');

        $this->assertResponseIsSuccessful();
    }
    */
}
