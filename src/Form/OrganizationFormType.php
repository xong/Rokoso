<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Organization::class]);
    }
}
