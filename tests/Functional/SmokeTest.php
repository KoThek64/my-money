<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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

            // Une valeur par défaut (ex. {type} = depense) respecte les contraintes de la route, pas un uuid.
            $parameters = [];
            foreach ($route->compile()->getPathVariables() as $variable) {
                $parameters[$variable] = $route->getDefault($variable) ?? self::ANY_UUID;
            }
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

        $transaction = new Transaction()
            ->setUser($user)
            ->setCategory($category)
            ->setAmount(1250)
            ->setLabel('Carrefour')
            ->setDate(new \DateTimeImmutable());

        $entityManager->persist($user);
        $entityManager->persist($category);
        $entityManager->persist($transaction);
        $entityManager->flush();
        $entityManager->clear();

        $client->loginUser($user);
        // %1$s : la catégorie, %2$s : la transaction.
        $client->request('GET', \sprintf($uri, $category->getId(), $transaction->getId()));

        self::assertResponseIsSuccessful();
    }

    /**
     * À fusionner dans pages() une fois les écrans de saisie codés.
     *
     * @return iterable<string, array{string}>
     */
    public static function transactionPages(): iterable
    {
        yield 'saisie (dépense par défaut)' => ['/transaction/create'];
        yield 'saisie d\'un revenu' => ['/transaction/create/revenu'];
        yield 'modification d\'une transaction' => ['/transaction/edit/%2$s'];
    }

    #[DataProvider('transactionPages')]
    #[Group('todo')]
    public function testTransactionPageRespondsForALoggedInUser(string $uri): void
    {
        $this->testPageRespondsForALoggedInUser($uri);
    }
}
