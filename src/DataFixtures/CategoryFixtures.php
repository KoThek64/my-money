<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\MovementKindEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CategoryFixtures extends Fixture implements DependentFixtureInterface
{
    private const int PER_USER = 3;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < UserFixtures::COUNT; ++$i) {
            $user = $this->getReference('user_'.$i, User::class);

            for ($j = 0; $j < self::PER_USER; ++$j) {
                $category = new Category();

                $category->setName(ucfirst($faker->word()))
                    ->setType($faker->randomElement(MovementKindEnum::cases()))
                    ->setColor($faker->hexColor())
                    ->setIcon('tabler:tag')
                    ->setUser($user)
                    ->setArchivedAt($faker->boolean(25) ? new \DateTimeImmutable() : null);

                $manager->persist($category);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
