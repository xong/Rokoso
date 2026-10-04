<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Mail\InternalMessageData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<InternalMessageData>
 */
final class InternalMessageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipients', EntityType::class, [
                'label' => 'internal.recipients',
                'class' => User::class,
                'choices' => $options['users'],
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                // validated via Count; HTML required on checkboxes would demand all of them
                'required' => false,
            ])
            ->add('subject', TextType::class, ['label' => 'compose.subject', 'empty_data' => ''])
            ->add('body', TextareaType::class, ['label' => 'compose.body', 'empty_data' => '', 'attr' => ['rows' => 10]])
            ->add('files', FileType::class, [
                'label' => 'compose.attachments',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [new Assert\All([new Assert\File(maxSize: '20M')])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InternalMessageData::class,
            'users' => [],
        ]);
    }
}
