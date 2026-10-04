<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Enum\Feature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Organization>
 */
final class OrganizationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'organization.name'])
            ->add('description', TextareaType::class, ['label' => 'organization.description', 'required' => false])
            ->add('color', ColorType::class, ['label' => 'organization.color'])
            ->add('logoFile', FileType::class, [
                'label' => 'organization.logo',
                'mapped' => false,
                'required' => false,
                'help' => 'form.image_help',
                'attr' => ['accept' => 'image/*'],
                'constraints' => [new Assert\Image(maxSize: '2M')],
            ])
            ->add('removeLogo', CheckboxType::class, [
                'label' => 'organization.remove_logo',
                'mapped' => false,
                'required' => false,
            ])
            ->add('enabledFeatures', EnumType::class, [
                'label' => 'organization.features_legend',
                'class' => Feature::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => static fn (Feature $feature): string => $feature->label(),
            ])
            ->add('quorumPercent', IntegerType::class, [
                'label' => 'organization.quorum_percent',
                'help' => 'organization.quorum_percent_help',
                'attr' => ['min' => 1, 'max' => 100],
            ])
            ->add('invitationDays', IntegerType::class, [
                'label' => 'organization.invitation_days',
                'help' => 'organization.invitation_days_help',
                'attr' => ['min' => 0, 'max' => 90],
            ])
            ->add('trashDays', IntegerType::class, [
                'label' => 'organization.trash_days',
                'help' => 'organization.trash_days_help',
                'attr' => ['min' => 1, 'max' => 365],
            ])
            ->add('messageRetentionYears', IntegerType::class, [
                'label' => 'organization.message_retention',
                'help' => 'organization.message_retention_help',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 30],
            ])
            ->add('submissionRetentionYears', IntegerType::class, [
                'label' => 'organization.submission_retention',
                'help' => 'organization.submission_retention_help',
                'required' => false,
                'attr' => ['min' => 1, 'max' => 30],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Organization::class]);
    }
}
