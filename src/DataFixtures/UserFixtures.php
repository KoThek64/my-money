<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\User\UserRegistrerService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class UserFixtures extends Fixture
{
    public const int COUNT = 10;
    public const string PASSWORD = 'password';

    public function __construct(private readonly UserRegistrerService $registrer)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < self::COUNT; ++$i) {
            $user = new User();
            $user->setEmail($faker->unique()->email())
                ->setRoles(['ROLE_USER']);

            $this->registrer->register($user, self::PASSWORD);

            $this->addReference('user_'.$i, $user);
        }
    }
}
