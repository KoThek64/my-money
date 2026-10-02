<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'category.form.name',
            ])
            ->add('type', null, [
                'label' => 'category.form.type',
            ])
            ->add('color', ColorType::class, [
                'label' => 'category.form.color',
            ])
            ->add('icon', null, [
                'label' => 'category.form.icon',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
