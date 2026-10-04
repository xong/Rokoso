<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use App\Entity\ContactGroup;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ContactGroup>
 */
final class ContactGroupFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'contact_group.name', 'empty_data' => ''])
            ->add('description', TextareaType::class, ['label' => 'contact_group.description', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('publicSubscribe', CheckboxType::class, ['label' => 'contact_group.public_subscribe', 'required' => false, 'help' => 'contact_group.public_subscribe_help'])
            ->add('contacts', EntityType::class, [
                'label' => 'contact_group.members',
                'class' => Contact::class,
                'choices' => $options['contacts'],
                'choice_label' => static fn (Contact $c): string => implode(' · ', array_filter([$c->getDisplayName(), $c->getFirstName() || $c->getLastName() ? $c->getCompany() : null, $c->getEmail()])),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactGroup::class, 'contacts' => []]);
        $resolver->setAllowedTypes('contacts', 'array');
    }
}
