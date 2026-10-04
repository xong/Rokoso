<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CalendarException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Change of a single occurrence: time, title and location (empty = as in the series).
 *
 * @extends AbstractType<CalendarException>
 */
final class CalendarExceptionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startsAt', DateTimeType::class, ['label' => 'calendar.starts_at', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endsAt', DateTimeType::class, ['label' => 'calendar.ends_at', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('title', TextType::class, ['label' => 'calendar.title', 'required' => false, 'help' => 'calendar.occurrence.same_as_series'])
            ->add('location', TextType::class, ['label' => 'calendar.location', 'required' => false, 'help' => 'calendar.occurrence.same_as_series']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CalendarException::class]);
    }
}
