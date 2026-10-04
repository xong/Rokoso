<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TextSnippet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TextSnippet>
 */
final class TextSnippetFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'snippet.name', 'empty_data' => ''])
            ->add('body', TextareaType::class, ['label' => 'snippet.body', 'empty_data' => '', 'help' => 'compose.body_help', 'attr' => ['rows' => 10]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TextSnippet::class]);
    }
}
