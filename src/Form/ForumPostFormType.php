<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ForumPost;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Markdown text of a post plus attachments (unmapped).
 *
 * @extends AbstractType<ForumPost>
 */
final class ForumPostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TextareaType::class, [
                'label' => 'forum.body',
                'help' => 'forum.markdown_help',
                'empty_data' => '',
                'attr' => ['rows' => 10, 'class' => 'font-mono'],
            ])
            ->add('files', FileType::class, [
                'label' => 'forum.attachments',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [new Assert\All([new Assert\File(maxSize: '50M')])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ForumPost::class]);
    }
}
