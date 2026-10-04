<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\WikiPage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<WikiPage>
 */
final class WikiPageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'wiki.page_title', 'attr' => ['maxlength' => 200]])
            ->add('parent', EntityType::class, [
                'label' => 'wiki.parent',
                'class' => WikiPage::class,
                'choices' => $options['pages'],
                'choice_label' => static fn (WikiPage $p): string => str_repeat('– ', \count($p->getPath()) - 1).$p->getTitle(),
                'required' => false,
                'placeholder' => 'wiki.no_parent',
            ])
            ->add('body', TextareaType::class, [
                'label' => 'wiki.body',
                'required' => false,
                'help' => 'wiki.body_help',
                'attr' => ['rows' => 18, 'class' => 'font-mono text-sm'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WikiPage::class,
            'pages' => [],
        ]);
        $resolver->setAllowedTypes('pages', 'array');
    }
}
