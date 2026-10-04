<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Entity\Resolution;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Resolution>
 */
final class ResolutionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $votes = ['required' => false, 'attr' => ['min' => 0, 'max' => 9999]];
        $builder
            ->add('title', TextType::class, ['label' => 'resolution.title'])
            ->add('text', TextareaType::class, ['label' => 'resolution.text', 'attr' => ['rows' => 5]])
            ->add('decidedOn', DateType::class, ['label' => 'resolution.decided_on', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('adopted', ChoiceType::class, [
                'label' => 'resolution.result',
                'choices' => ['resolution.adopted' => true, 'resolution.rejected' => false],
                'expanded' => true,
            ])
            ->add('votesYes', IntegerType::class, ['label' => 'resolution.votes_yes'] + $votes)
            ->add('votesNo', IntegerType::class, ['label' => 'resolution.votes_no'] + $votes)
            ->add('votesAbstain', IntegerType::class, ['label' => 'resolution.votes_abstain'] + $votes)
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ])
            ->add('public', CheckboxType::class, ['label' => 'resolution.public', 'required' => false, 'help' => 'resolution.public_help']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Resolution::class,
            'projects' => [],
        ]);
    }
}
