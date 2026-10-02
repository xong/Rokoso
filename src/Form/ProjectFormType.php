<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\OrganizationRole;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
 * @extends AbstractType<Project>
 */
final class ProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $user */
        $user = $options['user'];

        $builder
            ->add('name', TextType::class, ['label' => 'project.name'])
            ->add('organization', EntityType::class, [
                'label' => 'project.organization',
                'class' => Organization::class,
                'required' => false,
                'placeholder' => 'project.no_organization',
                'help' => 'project.organization_help',
                // Nur Organisationen, in denen der Benutzer Administrator ist
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('o')
                    ->join(Membership::class, 'm', 'WITH', 'm.organization = o AND m.user = :user AND m.role = :admin')
                    ->setParameter('user', $user)
                    ->setParameter('admin', OrganizationRole::Admin->value)
                    ->orderBy('o.name'),
            ])
            ->add('description', TextareaType::class, ['label' => 'project.description', 'required' => false])
            ->add('color', ColorType::class, ['label' => 'project.color'])
            ->add('imageFile', FileType::class, [
                'label' => 'project.image',
                'mapped' => false,
                'required' => false,
                'help' => 'form.image_help',
                'attr' => ['accept' => 'image/*'],
                'constraints' => [new Assert\Image(maxSize: '2M')],
            ])
            ->add('removeImage', CheckboxType::class, [
                'label' => 'project.remove_image',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Project::class]);
        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', User::class);
    }
}
