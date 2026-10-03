<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CalendarItem;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\CalendarItemType;
use App\Enum\Recurrence;
use App\Enum\TaskStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CalendarItem>
 */
final class CalendarItemFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $users = [
            'class' => User::class,
            'choices' => $options['users'],
            'choice_label' => 'name',
            'multiple' => true,
            'expanded' => true,
            'required' => false,
        ];

        $builder
            ->add('type', EnumType::class, [
                'label' => 'calendar.type.label',
                'class' => CalendarItemType::class,
                'choice_label' => static fn (CalendarItemType $t): string => $t->label(),
                'expanded' => true,
            ])
            ->add('title', TextType::class, ['label' => 'calendar.title'])
            ->add('allDay', CheckboxType::class, ['label' => 'calendar.all_day', 'required' => false])
            ->add('startsAt', DateTimeType::class, ['label' => 'calendar.starts_at', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false, 'help' => 'calendar.starts_at_help'])
            ->add('endsAt', DateTimeType::class, ['label' => 'calendar.ends_at', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('location', TextType::class, ['label' => 'calendar.location', 'required' => false])
            ->add('organization', EntityType::class, [
                'label' => 'calendar.organization',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'calendar.personal',
                'help' => 'calendar.organization_help',
            ])
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ])
            ->add('description', TextareaType::class, ['label' => 'calendar.description', 'required' => false])
            ->add('url', UrlType::class, ['label' => 'calendar.url', 'required' => false, 'default_protocol' => 'https'])
            ->add('assignees', EntityType::class, ['label' => 'calendar.assignees'] + $users)
            ->add('participants', EntityType::class, ['label' => 'calendar.participants'] + $users)
            ->add('recurrence', EnumType::class, [
                'label' => 'calendar.recurrence.label',
                'class' => Recurrence::class,
                'choice_label' => static fn (Recurrence $r): string => $r->label(),
            ])
            ->add('recurrenceInterval', IntegerType::class, ['label' => 'calendar.recurrence.interval', 'attr' => ['min' => 1, 'max' => 99]])
            ->add('recurrenceUntil', DateType::class, ['label' => 'calendar.recurrence.until', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('status', EnumType::class, [
                'label' => 'task.status.label',
                'class' => TaskStatus::class,
                'choice_label' => static fn (TaskStatus $s): string => $s->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CalendarItem::class,
            'users' => [],
            'organizations' => [],
            'projects' => [],
        ]);
    }
}
