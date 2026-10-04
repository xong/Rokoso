<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Entity\Poll;
use App\Entity\Project;
use App\Enum\PollKind;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * New poll. Options (choice) and date slots (schedule) are unmapped and turned into PollOptions by the controller.
 *
 * @extends AbstractType<Poll>
 */
final class PollFormType extends AbstractType
{
    public const int SLOTS = 6;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'poll.title'])
            ->add('description', TextareaType::class, ['label' => 'poll.description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('kind', EnumType::class, [
                'label' => 'poll.kind.label',
                'class' => PollKind::class,
                'expanded' => true,
                'choice_label' => static fn (PollKind $kind): string => $kind->label(),
                'choice_attr' => static fn (): array => ['data-action' => 'choice-sections#toggle'],
            ])
            ->add('organization', EntityType::class, [
                'label' => 'poll.organization',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
                'disabled' => $options['lock_organization'],
            ])
            ->add('options', TextareaType::class, [
                'label' => 'poll.options',
                'help' => 'poll.options_help',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 4],
                'constraints' => [new Assert\Length(max: 5000)],
            ])
            ->add('multiple', CheckboxType::class, ['label' => 'poll.multiple', 'required' => false])
            ->add('slotMinutes', IntegerType::class, [
                'label' => 'poll.slot_minutes',
                'mapped' => false,
                'required' => false,
                'data' => 120,
                'attr' => ['min' => 0, 'max' => 1440],
                'constraints' => [new Assert\Range(min: 0, max: 1440)],
            ]);
        for ($i = 1; $i <= self::SLOTS; ++$i) {
            $builder->add('slot'.$i, DateTimeType::class, [
                'label' => 'poll.slot_n',
                'label_translation_parameters' => ['%n%' => $i],
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'mapped' => false,
                'required' => false,
            ]);
        }
        $builder
            ->add('secret', CheckboxType::class, ['label' => 'poll.secret', 'help' => 'poll.secret_help', 'required' => false])
            ->add('votingOnly', CheckboxType::class, ['label' => 'poll.voting_only', 'help' => 'poll.voting_only_help', 'required' => false])
            ->add('circular', CheckboxType::class, ['label' => 'poll.circular', 'help' => 'poll.circular_help', 'required' => false])
            ->add('deadline', DateTimeType::class, ['label' => 'poll.deadline', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Poll::class,
            'organizations' => [],
            'projects' => [],
            'lock_organization' => false,
        ]);
    }
}
