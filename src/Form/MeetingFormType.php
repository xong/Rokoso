<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Meeting;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Meeting>
 */
final class MeetingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'meeting.title'])
            ->add('organization', EntityType::class, [
                'label' => 'meeting.organization',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
                'disabled' => $options['lock_organization'],
            ])
            ->add('startsAt', DateTimeType::class, ['label' => 'meeting.starts_at', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endsAt', DateTimeType::class, ['label' => 'meeting.ends_at', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false, 'help' => 'meeting.ends_at_help'])
            ->add('location', TextType::class, ['label' => 'meeting.location', 'required' => false])
            ->add('videoUrl', UrlType::class, ['label' => 'meeting.video_url', 'required' => false, 'default_protocol' => 'https'])
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ])
            ->add('minuteTaker', EntityType::class, [
                'label' => 'meeting.minute_taker',
                'class' => User::class,
                'choices' => $options['users'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'meeting.minute_taker_none',
            ])
            ->add('description', TextareaType::class, ['label' => 'meeting.description', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('guestEmails', TextareaType::class, ['label' => 'meeting.guests', 'required' => false, 'help' => 'meeting.guests_help', 'attr' => ['rows' => 2]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Meeting::class,
            'organizations' => [],
            'projects' => [],
            'users' => [],
            'lock_organization' => false,
        ]);
    }
}
