<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Folder;
use App\Entity\Project;
use App\Entity\StoredFile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Rename, move (within the organization) and assign a project.
 *
 * @extends AbstractType<StoredFile>
 */
final class FileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('filename', TextType::class, ['label' => 'file.name'])
            ->add('folder', EntityType::class, [
                'label' => 'file.folder',
                'class' => Folder::class,
                'choices' => $options['folders'],
                'choice_label' => static fn (Folder $f): string => implode(' / ', array_map(static fn (Folder $p): string => $p->getName(), $f->getPath())),
            ])
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StoredFile::class,
            'folders' => [],
            'projects' => [],
        ]);
        $resolver->setAllowedTypes('folders', 'array');
        $resolver->setAllowedTypes('projects', 'array');
    }
}
