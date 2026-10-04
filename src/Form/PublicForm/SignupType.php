<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Participation\PublicGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Signup for a public event.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SignupType extends AbstractType
{
    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'public.field.name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)], 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'public.field.email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)], 'attr' => ['autocomplete' => 'email']])
            ->add('persons', IntegerType::class, ['label' => 'public.field.persons', 'data' => 1, 'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 10)], 'attr' => ['min' => 1, 'max' => 10]])
            ->add('consent', CheckboxType::class, ['label' => 'public.field.consent', 'constraints' => [new Assert\IsTrue(message: 'public.consent_required')]]);
        $this->guard->addFields($builder);
    }
}
