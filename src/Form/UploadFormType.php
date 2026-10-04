<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Multiple file upload (with drag & drop via the form theme); with `version` a single file
 * that replaces the content of an existing one.
 *
 * @extends AbstractType<array{files: list<\Symfony\Component\HttpFoundation\File\UploadedFile>|\Symfony\Component\HttpFoundation\File\UploadedFile|null}>
 */
final class UploadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('files', FileType::class, $options['version'] ? [
            'label' => 'file.version.upload',
            'constraints' => [
                new Assert\NotNull(message: 'file.none_selected'),
                new Assert\File(maxSize: '50M'),
            ],
        ] : [
            'label' => 'file.upload',
            'multiple' => true,
            'constraints' => [
                new Assert\Count(min: 1, minMessage: 'file.none_selected'),
                new Assert\All([new Assert\File(maxSize: '50M')]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['version' => false]);
        $resolver->setAllowedTypes('version', 'bool');
    }
}
