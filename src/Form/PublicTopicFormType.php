<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Entity\PublicTopic;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PublicTopic>
 */
final class PublicTopicFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'public.topic.name', 'empty_data' => ''])
            ->add('project', EntityType::class, [
                'label' => 'public.topic.project',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ])
            ->add('assignee', EntityType::class, [
                'label' => 'public.topic.assignee',
                'class' => User::class,
                'choices' => $options['users'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'public.topic.assignee_none',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PublicTopic::class, 'projects' => [], 'users' => []]);
        $resolver->setAllowedTypes('projects', 'array');
        $resolver->setAllowedTypes('users', 'array');
    }
}
