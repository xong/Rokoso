<?php

declare(strict_types=1);

namespace App\Form;

use App\Facility\FacilityPerson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<FacilityPerson>
 */
final class FacilityPersonFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'contact.first_name', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('lastName', TextType::class, ['label' => 'contact.last_name', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('email', EmailType::class, ['label' => 'contact.email', 'required' => false, 'help' => 'facility.person_email_help', 'attr' => ['autocomplete' => 'off']])
            ->add('phone', TelType::class, ['label' => 'contact.phone', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('reportedOn', DateType::class, [
                'label' => 'facility.reported_on',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'facility.reported_on_help',
            ]);
        if (null !== $options['previous']) {
            $builder->add('removePrevious', CheckboxType::class, [
                'label' => 'facility.remove_previous',
                'label_translation_parameters' => ['%name%' => $options['previous']],
                'required' => false,
                'help' => 'facility.remove_previous_help',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // name of the person replaced, if they may be deleted (no other seat)
        $resolver->setDefaults(['data_class' => FacilityPerson::class, 'previous' => null]);
        $resolver->setAllowedTypes('previous', ['null', 'string']);
    }
}
