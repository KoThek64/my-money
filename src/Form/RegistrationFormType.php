<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'registration.email',
                'constraints' => [
                    new NotBlank(
                        message: 'user.email.not_blank',
                    ),
                    new Email(
                        message: 'user.email.invalid',
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'registration.agree_terms',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'user.terms.must_agree',
                    ),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'registration.password',
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(
                        message: 'user.password.not_blank',
                    ),
                    // Longueur plutôt que composition (NIST) ; le max n'est qu'un
                    // garde-fou contre un hachage d'entrée démesurée.
                    new Length(
                        min: 12,
                        max: 4096,
                        minMessage: 'user.password.too_short',
                    ),
                    // minScore explicite : le défaut MEDIUM (≈80 bits) recale encore
                    // 12 caractères mêlant les 4 classes. WEAK ≈60 bits.
                    new PasswordStrength(
                        minScore: PasswordStrength::STRENGTH_WEAK,
                        message: 'user.password.too_weak',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
