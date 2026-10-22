<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\MovementKindEnum;
use App\Repository\CategoryRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'transaction.form.amount',
                'divisor' => 100,
                'input' => 'integer',
                'invalid_message' => 'transaction.amount.invalid',
            ])
            ->add('label', null, [
                'label' => 'transaction.form.label',
                'required' => false,
            ])
            ->add('date', null, [
                'label' => 'transaction.form.date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('category', EntityType::class, [
                'label' => 'transaction.form.category.label',
                'class' => Category::class,
                'choice_label' => 'name',
                'query_builder' => function (CategoryRepository $repository) use ($options): QueryBuilder {
                    $qb = $repository->createActiveQueryBuilder($options['type']);

                    $transaction = $options['data'] ?? null;
                    $current = $transaction?->getCategory();
                    if ($current instanceof Category) {
                        $qb->orWhere('c = :current')
                            ->setParameter('current', $current);
                    }

                    return $qb;
                },
                'placeholder' => 'transaction.form.category.placeholder',
            ]);

        $builder->get('amount')->addViewTransformer(new CallbackTransformer(
            fn (?string $text): ?string => $text,
            function (?string $text): ?string {
                if (null === $text) {
                    return null;
                }

                if (preg_match('/[,.]\d{3,}/', $text)) {
                    throw new TransformationFailedException();
                }

                return $text;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
        ]);
        $resolver->setRequired('type')->setAllowedTypes('type', MovementKindEnum::class);
    }
}
