<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ForumTopic;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Title and project of a topic; for a new topic the first post is embedded.
 *
 * @extends AbstractType<ForumTopic>
 */
final class ForumTopicFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'forum.topic_title', 'attr' => ['autofocus' => true]])
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ]);
        if (null !== $options['first_post']) {
            $builder->add('post', ForumPostFormType::class, [
                'mapped' => false,
                'data' => $options['first_post'],
                'label' => false,
                'constraints' => [new Assert\Valid()],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumTopic::class,
            'projects' => [],
            'first_post' => null,
        ]);
        $resolver->setAllowedTypes('projects', 'array');
        $resolver->setAllowedTypes('first_post', ['null', 'App\Entity\ForumPost']);
    }
}
