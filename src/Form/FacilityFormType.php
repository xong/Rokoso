<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Facility;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Facility>
 */
final class FacilityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $text = static fn (string $label, array $extra = []): array => ['label' => 'facility.'.$label, 'required' => false] + $extra;

        $builder
            ->add('name', TextType::class, ['label' => 'facility.name', 'empty_data' => ''])
            ->add('number', TextType::class, $text('number', ['help' => 'facility.number_help', 'attr' => ['autocomplete' => 'off']]))
            ->add('carrier', TextType::class, $text('carrier', ['attr' => ['list' => 'facility-carriers', 'autocomplete' => 'off']]))
            ->add('district', TextType::class, $text('district', ['attr' => ['list' => 'facility-districts', 'autocomplete' => 'off']]))
            ->add('street', TextType::class, $text('street'))
            ->add('postalCode', TextType::class, $text('postal_code'))
            ->add('city', TextType::class, $text('city'))
            ->add('email', EmailType::class, $text('email', ['help' => 'facility.email_help']))
            ->add('phone', TelType::class, $text('phone'))
            ->add('notes', TextareaType::class, $text('notes'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Facility::class]);
    }
}
