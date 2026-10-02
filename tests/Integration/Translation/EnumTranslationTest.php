<?php

declare(strict_types=1);

namespace App\Tests\Integration\Translation;

use App\Enum\GoalScopeEnum;
use App\Enum\MovementKindEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EnumTranslationTest extends KernelTestCase
{
    // Une clé absente ou mal construite s'afficherait brute dans les listes déroulantes.
    #[DataProvider('caseProvider')]
    public function testEveryCaseIsTranslated(TranslatableInterface $case, string $expected): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame($expected, $case->trans($translator));
    }

    /**
     * @return iterable<string, array{TranslatableInterface, string}>
     */
    public static function caseProvider(): iterable
    {
        $labels = [
            MovementKindEnum::DEPENSE->name => 'Dépense',
            MovementKindEnum::REVENU->name => 'Revenu',
            GoalScopeEnum::DEPENSE_GLOBALE->name => 'Dépense globale',
            GoalScopeEnum::DEPENSE_CATEGORIE->name => 'Dépense catégorie',
            GoalScopeEnum::EPARGNE->name => 'Épargne',
        ];

        // Parcourir cases() fait échouer le test si un case est ajouté sans libellé attendu.
        foreach ([...MovementKindEnum::cases(), ...GoalScopeEnum::cases()] as $case) {
            yield $case::class.'::'.$case->name => [$case, $labels[$case->name] ?? ''];
        }
    }
}
