<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ForumBoard;
use App\Entity\Organization;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Name, description, organization (only for top-level areas) and optional project.
 *
 * @extends AbstractType<ForumBoard>
 */
final class ForumBoardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'forum.board_name', 'attr' => ['autofocus' => true]])
            ->add('description', TextareaType::class, ['label' => 'forum.board_description', 'required' => false, 'attr' => ['rows' => 2]]);
        if ($options['root']) {
            $builder->add('organization', EntityType::class, [
                'label' => 'folder.organization',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
                'help' => 'forum.organization_help',
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
            'data_class' => ForumBoard::class,
            'root' => false,
            'organizations' => [],
            'projects' => [],
        ]);
        $resolver->setAllowedTypes('root', 'bool');
        $resolver->setAllowedTypes('organizations', 'array');
        $resolver->setAllowedTypes('projects', 'array');
    }
}
