<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DefaultCategoryInstaller;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Parcours d'authentification : inscription, connexion, protection des routes (RG-1).
 */
final class AuthenticationTest extends WebTestCase
{
    private const string EMAIL = 'testeur@my-money.test';
    private const string PASSWORD = 'Corr3ct-H0rse-Battery';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        // Pas de DAMA/DoctrineTestBundle ici : on repart d'une table user vide.
        // Les FK user_id sont en CASCADE, le reste des données suit.
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement('DELETE FROM "user"');
    }

    private function createUser(): User
    {
        $container = self::getContainer();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail(self::EMAIL);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function findUser(): ?User
    {
        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);

        return $repository->findOneBy(['email' => self::EMAIL]);
    }

    public function testRegistrationCreatesUserWithHashedPassword(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => self::EMAIL,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]));

        self::assertResponseRedirects('/login');

        $user = $this->findUser();
        self::assertInstanceOf(User::class, $user, "L'inscription n'a pas créé le compte.");

        // Le point qui compte : le mot de passe ne doit jamais être stocké en clair.
        $storedPassword = $user->getPassword();
        self::assertIsString($storedPassword);
        self::assertNotSame(self::PASSWORD, $storedPassword, 'Le mot de passe est stocké en clair !');

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue(
            $hasher->isPasswordValid($user, self::PASSWORD),
            'Le hachage enregistré ne correspond pas au mot de passe saisi.',
        );
    }

    /**
     * RG-4 — le jeu de catégories de base arrive avec le compte, pas plus tard.
     */
    public function testRegistrationInstallsTheDefaultCategories(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => self::EMAIL,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]));

        self::assertResponseRedirects('/login');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $count = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM category c JOIN "user" u ON u.id = c.user_id WHERE u.email = ?',
            [self::EMAIL],
        );

        self::assertSame(DefaultCategoryInstaller::count(), $count);
    }

    /**
     * Un compte refusé ne doit laisser aucune catégorie derrière lui : les
     * catégories partagent le flush du compte.
     */
    public function testRejectedRegistrationLeavesNoCategory(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => self::EMAIL,
            'registration_form[plainPassword]' => 'motdepasse12',
            'registration_form[agreeTerms]' => true,
        ]));

        self::assertResponseStatusCodeSame(422);

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM category'));
    }

    public function testRegistrationRejectsAnAlreadyUsedEmail(): void
    {
        $this->createUser();

        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => self::EMAIL,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]));

        // 422 : Symfony réaffiche le formulaire invalide sans rediriger.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Un compte existe déjà avec cette adresse email');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM "user"'), 'Un doublon a été créé.');
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function passwordProvider(): iterable
    {
        // Refusés : trop courts ou trop pauvres, quoi qu'il arrive.
        yield 'trop court' => ['Ab1!xyz', false];
        yield 'mot commun avec chiffres' => ['motdepasse12', false];
        yield 'une seule classe de caractères' => ['azertyuiopqs', false];

        // Acceptés : 12 caractères mêlant les 4 classes, et une passphrase.
        yield '12 caractères, 4 classes' => ['13247780Hm!!', true];
        yield 'passphrase' => ['Corr3ct-H0rse-Battery', true];
    }

    #[DataProvider('passwordProvider')]
    public function testRegistrationEnforcesPasswordPolicy(string $password, bool $shouldBeAccepted): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => self::EMAIL,
            'registration_form[plainPassword]' => $password,
            'registration_form[agreeTerms]' => true,
        ]));

        if ($shouldBeAccepted) {
            self::assertResponseRedirects('/login');
            self::assertInstanceOf(User::class, $this->findUser());

            return;
        }

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->findUser(), 'Un mot de passe refusé a quand même créé le compte.');
        // Le refus doit être VISIBLE et rattaché AU BON champ : un 422 muet est
        // indébuggable côté utilisateur (c'est exactement ce qui s'est produit).
        self::assertSelectorExists('li[id^="registration_form_plainPassword_error"]');
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $this->createUser();

        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => self::PASSWORD,
        ]));

        self::assertResponseRedirects('/');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', self::EMAIL);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->createUser();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => 'ce-nest-pas-le-bon-mot-de-passe',
        ]));

        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        // On cible la classe, pas le texte : le catalogue `security` est traduit
        // par Symfony et la langue affichée dépend de default_locale.
        self::assertSelectorExists('.form-error');

        // Et surtout : toujours pas de session ouverte.
        self::assertNull(self::getContainer()->get('security.token_storage')->getToken());
    }

    /**
     * RG-1 — une route non ouverte explicitement exige une session.
     */
    public function testProtectedRouteRedirectsAnonymousVisitorToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects();
        self::assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAuthenticatedUserIsRedirectedAwayFromLoginAndRegister(): void
    {
        $this->client->loginUser($this->createUser());

        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/register');
        self::assertResponseRedirects('/');
    }
}
