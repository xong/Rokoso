<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

/**
 * Current password as confirmation for sensitive actions (disable 2FA, delete account).
 *
 * @extends AbstractType<mixed>
 */
final class ConfirmPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'user.current_password',
            'attr' => ['autocomplete' => 'current-password'],
            'constraints' => [new UserPassword(message: 'user.wrong_password')],
        ]);
    }
}
