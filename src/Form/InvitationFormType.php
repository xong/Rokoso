<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\OrganizationRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array{email: string, role: OrganizationRole}>
 */
final class InvitationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.email',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('role', EnumType::class, [
                'label' => 'organization.role.label',
                'class' => OrganizationRole::class,
                'choice_label' => static fn (OrganizationRole $role): string => $role->label(),
                'data' => OrganizationRole::Member,
            ]);
    }
}
