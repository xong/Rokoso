<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\Theme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'user.name',
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'user.avatar',
                'mapped' => false,
                'required' => false,
                'help' => 'form.image_help',
                'attr' => ['accept' => 'image/*'],
                'constraints' => [new Assert\Image(maxSize: '2M')],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'label' => 'user.remove_avatar',
                'mapped' => false,
                'required' => false,
            ])
            ->add('theme', EnumType::class, [
                'label' => 'user.theme.label',
                'class' => Theme::class,
                'choice_label' => static fn (Theme $t): string => $t->label(),
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
