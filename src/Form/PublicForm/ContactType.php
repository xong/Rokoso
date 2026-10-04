<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Entity\PublicTopic;
use App\Participation\PublicGuard;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Public contact form; the submission ends up in the organization's inbox.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ContactType extends AbstractType
{
    public const int MAX_FILES = 3;

    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'public.field.name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)], 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'public.field.email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)], 'attr' => ['autocomplete' => 'email']])
            ->add('institution', TextType::class, ['label' => 'public.field.institution', 'required' => false, 'help' => 'public.field.institution_help', 'constraints' => [new Assert\Length(max: 200)], 'attr' => ['autocomplete' => 'organization']]);
        if ([] !== $options['topics']) {
            $builder->add('topic', EntityType::class, [
                'label' => 'public.field.topic',
                'class' => PublicTopic::class,
                'choices' => $options['topics'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'public.field.topic_none',
            ]);
        }
        $builder
            ->add('subject', TextType::class, ['label' => 'public.field.subject', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 200)]])
            ->add('message', TextareaType::class, ['label' => 'public.field.message', 'attr' => ['rows' => 8], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20000)]])
            ->add('attachments', FileType::class, [
                'label' => 'public.field.attachments',
                'help' => 'public.field.attachments_help',
                'help_translation_parameters' => ['%count%' => self::MAX_FILES],
                'multiple' => true,
                'required' => false,
                'constraints' => [
                    new Assert\Count(max: self::MAX_FILES),
                    new Assert\All([new Assert\File(maxSize: '5M', extensions: ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'odt', 'xlsx', 'ods', 'txt'])]),
                ],
            ])
            ->add('copy', CheckboxType::class, ['label' => 'public.field.copy', 'required' => false])
            ->add('consent', CheckboxType::class, ['label' => 'public.field.consent', 'constraints' => [new Assert\IsTrue(message: 'public.consent_required')]]);
        $this->guard->addFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['topics' => []]);
        $resolver->setAllowedTypes('topics', 'array');
    }
}
