<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * New password with confirmation field and strength rules.
 *
 * @extends AbstractType<string>
 */
final class NewPasswordType extends AbstractType
{
    public function getParent(): string
    {
        return RepeatedType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'invalid_message' => 'user.password_mismatch',
            // Fehler am ersten Feld anzeigen, damit sie neben der Eingabe stehen.
            'error_mapping' => ['.' => 'first'],
            'first_options' => [
                'label' => 'user.new_password',
                'help' => 'user.password_help',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'user.repeat_password',
                'attr' => ['autocomplete' => 'new-password'],
            ],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 10, max: 4096),
                new Assert\NotCompromisedPassword(skipOnError: true),
            ],
        ]);
    }
}
