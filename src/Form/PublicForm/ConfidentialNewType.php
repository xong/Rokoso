<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Participation\PublicGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Starts an anonymous confidential conversation; the email address is optional and only used for reply notices.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ConfidentialNewType extends AbstractType
{
    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, ['label' => 'public.field.subject', 'help' => 'public.confidential.subject_help', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 200)], 'attr' => ['autocomplete' => 'off']])
            ->add('message', TextareaType::class, ['label' => 'public.field.message', 'attr' => ['rows' => 8], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20000)]])
            ->add('email', EmailType::class, ['label' => 'public.confidential.email', 'help' => 'public.confidential.email_help', 'required' => false, 'constraints' => [new Assert\Email(), new Assert\Length(max: 180)], 'attr' => ['autocomplete' => 'off']])
            ->add('consent', CheckboxType::class, ['label' => 'public.field.consent', 'constraints' => [new Assert\IsTrue(message: 'public.consent_required')]]);
        $this->guard->addFields($builder);
    }
}
