<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Membership;
use App\Entity\Project;
use App\Enum\OrganizationRole;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Role, function, term and (for guests) the released projects of a membership.
 *
 * @extends AbstractType<Membership>
 */
final class MembershipFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', EnumType::class, [
                'label' => 'organization.role.label',
                'class' => OrganizationRole::class,
                'choice_label' => static fn (OrganizationRole $r): string => $r->label(),
                'expanded' => true,
                'choice_attr' => static fn (): array => ['data-action' => 'choice-sections#toggle'],
            ])
            ->add('position', TextType::class, [
                'label' => 'membership.position',
                'required' => false,
                'help' => 'membership.position_help',
                'attr' => ['list' => 'membership-positions', 'maxlength' => 80],
            ])
            ->add('termEndsOn', DateType::class, [
                'label' => 'membership.term_ends_on',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'membership.term_ends_on_help',
            ])
            ->add('votingRight', CheckboxType::class, [
                'label' => 'organization.voting_right',
                'required' => false,
                'help' => 'membership.voting_right_help',
            ])
            ->add('confidant', CheckboxType::class, [
                'label' => 'membership.confidant',
                'required' => false,
                'help' => 'membership.confidant_help',
            ])
            ->add('guestProjects', EntityType::class, [
                'label' => 'membership.guest_projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'membership.guest_projects_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membership::class,
            'projects' => [],
        ]);
        $resolver->setAllowedTypes('projects', 'array');
    }
}
