<?php

namespace App\Tests\Unit\Form;

use App\Form\CategoryEditType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

class CategoryEditTypeTest extends TestCase
{
    // Le contrôleur passe toujours l'option : ce défaut ne sert qu'en cas d'oubli, qui doit figer le type.
    public function testTypeIsNotEditableByDefault(): void
    {
        $form = Forms::createFormFactory()->create(CategoryEditType::class);

        $this->assertFalse($form->has('type'));
    }
}
