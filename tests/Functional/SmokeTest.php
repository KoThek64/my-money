<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Smoke — le projet tient debout : routes protégées, pages qui répondent.
 */
final class SmokeTest extends WebTestCase
{
    /**
     * Ouvrir une route est un geste conscient : l'ajouter ici en fait partie.
     */
    private const array PUBLIC_ROUTES = ['app_login', 'app_register'];

    private const string ANY_UUID = '0197c3e0-0000-7000-8000-000000000001';

    /**
     * RG-1 — la liste vient du routeur : une route ajoutée demain est couverte d'office.
     */
    public function testEveryNonPublicRouteSendsAnonymousVisitorsToLogin(): void
    {
        $client = self::createClient();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get(RouterInterface::class);

        $requests = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (\in_array($name, self::PUBLIC_ROUTES, true)) {
                continue;
            }

            $parameters = array_fill_keys($route->compile()->getPathVariables(), self::ANY_UUID);
            $requests[$name] = [$route->getMethods()[0] ?? 'GET', $router->generate($name, $parameters)];
        }

        self::assertArrayHasKey('app_home', $requests);

        foreach ($requests as $name => [$method, $uri]) {
            $client->request($method, $uri);

            self::assertResponseRedirects('/login', null, \sprintf('La route « %s » (%s %s) est ouverte aux anonymes.', $name, $method, $uri));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        yield 'liste des catégories' => ['/category/show-all'];
        yield 'formulaire de création' => ['/category/create'];
        yield 'consultation' => ['/category/show/%s'];
        yield 'formulaire de modification' => ['/category/edit/%s'];
    }

    /**
     * Pas d'assertion métier : seulement « la page s'affiche » (template cassé, service mal câblé).
     */
    #[DataProvider('pages')]
    public function testPageRespondsForALoggedInUser(string $uri): void
    {
        $client = self::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM "user"');

        $user = new User();
        $user->setEmail('alice@my-money.test')
            ->setPassword('irrelevant');

        $category = new Category();
        $category->setName('Courses')
            ->setType(MovementKindEnum::DEPENSE)
            ->setColor('#000000')
            ->setIcon('tabler:cart')
            ->setUser($user);

        $entityManager->persist($user);
        $entityManager->persist($category);
        $entityManager->flush();
        $entityManager->clear();

        $client->loginUser($user);
        $client->request('GET', \sprintf($uri, $category->getId()));

        self::assertResponseIsSuccessful();
    }
}
