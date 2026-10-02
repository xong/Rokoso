<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<Contact>
 */
final class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $text = static fn (string $label, array $extra = []): array => ['label' => 'contact.'.$label, 'required' => false] + $extra;

        $builder
            ->add('organization', EntityType::class, [
                'label' => 'contact.organization',
                'class' => Organization::class,
                'choices' => $options['organizations'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'contact.private',
                'help' => 'contact.organization_help',
            ])
            ->add('salutation', TextType::class, $text('salutation', ['attr' => ['autocomplete' => 'off']]))
            ->add('firstName', TextType::class, $text('first_name'))
            ->add('lastName', TextType::class, $text('last_name'))
            ->add('company', TextType::class, $text('company'))
            ->add('position', TextType::class, $text('position'))
            ->add('email', EmailType::class, $text('email'))
            ->add('email2', EmailType::class, $text('email2'))
            ->add('phone', TelType::class, $text('phone'))
            ->add('mobile', TelType::class, $text('mobile'))
            ->add('street', TextType::class, $text('street'))
            ->add('postalCode', TextType::class, $text('postal_code'))
            ->add('city', TextType::class, $text('city'))
            ->add('website', UrlType::class, $text('website', ['default_protocol' => 'https']))
            ->add('birthday', BirthdayType::class, $text('birthday', ['widget' => 'single_text', 'input' => 'datetime_immutable']))
            ->add('tags', TextType::class, $text('tags', ['help' => 'contact.tags_help']))
            ->add('notes', TextareaType::class, $text('notes'))
            ->add('photoFile', FileType::class, [
                'label' => 'contact.photo',
                'mapped' => false,
                'required' => false,
                'help' => 'form.image_help',
                'attr' => ['accept' => 'image/*'],
                'constraints' => [new Assert\Image(maxSize: '2M')],
            ])
            ->add('removePhoto', CheckboxType::class, ['label' => 'contact.remove_photo', 'mapped' => false, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contact::class, 'organizations' => []]);
        $resolver->setAllowedTypes('organizations', 'array');
    }
}
