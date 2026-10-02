<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Multiple file upload (with drag & drop via the form theme).
 *
 * @extends AbstractType<array{files: list<\Symfony\Component\HttpFoundation\File\UploadedFile>}>
 */
final class UploadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('files', FileType::class, [
            'label' => 'file.upload',
            'multiple' => true,
            'constraints' => [
                new Assert\Count(min: 1, minMessage: 'file.none_selected'),
                new Assert\All([new Assert\File(maxSize: '50M')]),
            ],
        ]);
    }
}
