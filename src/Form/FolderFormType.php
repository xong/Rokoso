<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Folder;
use App\Entity\Organization;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Name, organization (only for top-level folders) and optional project.
 *
 * @extends AbstractType<Folder>
 */
final class FolderFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['label' => 'folder.name', 'attr' => ['autofocus' => true]]);
        if ($options['root']) {
            $builder->add('organization', EntityType::class, [
                'label' => 'folder.organization',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
                'help' => 'folder.organization_help',
            ]);
        }
        $builder->add('project', EntityType::class, [
            'label' => 'nav.projects',
            'class' => Project::class,
            'choices' => $options['projects'],
            'choice_label' => static fn (Project $p): string => $p->getName().' ('.$p->getOrganization()?->getName().')',
            'required' => false,
            'placeholder' => 'mail.project.none',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Folder::class,
            'root' => false,
            'organizations' => [],
            'projects' => [],
        ]);
        $resolver->setAllowedTypes('root', 'bool');
        $resolver->setAllowedTypes('organizations', 'array');
        $resolver->setAllowedTypes('projects', 'array');
    }
}
