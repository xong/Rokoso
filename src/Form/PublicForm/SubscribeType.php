<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Entity\ContactGroup;
use App\Participation\PublicGuard;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Subscription to public distribution lists (double opt-in).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SubscribeType extends AbstractType
{
    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'public.field.name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)], 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'public.field.email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)], 'attr' => ['autocomplete' => 'email']])
            ->add('groups', EntityType::class, [
                'label' => 'public.field.groups',
                'class' => ContactGroup::class,
                'choices' => $options['groups'],
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'data' => 1 === \count($options['groups']) ? $options['groups'] : [],
                'constraints' => [new Assert\Count(min: 1, minMessage: 'public.groups_required')],
            ])
            ->add('consent', CheckboxType::class, ['label' => 'public.field.consent', 'constraints' => [new Assert\IsTrue(message: 'public.consent_required')]]);
        $this->guard->addFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['groups' => []]);
        $resolver->setAllowedTypes('groups', 'array');
    }
}
