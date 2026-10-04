<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Entity\Survey;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Survey>
 */
final class SurveyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'survey.title', 'empty_data' => ''])
            ->add('description', TextareaType::class, ['label' => 'survey.description', 'help' => 'survey.description_help', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('anonymous', CheckboxType::class, ['label' => 'survey.anonymous', 'help' => 'survey.anonymous_help', 'required' => false])
            ->add('listed', CheckboxType::class, ['label' => 'survey.listed', 'help' => 'survey.listed_help', 'required' => false]);
        if ([] !== $options['organizations']) {
            $builder->add('organization', EntityType::class, [
                'label' => 'nav.organizations',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Survey::class, 'organizations' => []]);
        $resolver->setAllowedTypes('organizations', 'array');
    }
}
