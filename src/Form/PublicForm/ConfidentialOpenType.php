<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Participation\PublicGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Opens a confidential conversation with its access code.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ConfidentialOpenType extends AbstractType
{
    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'public.confidential.code',
            'help' => 'public.confidential.code_help',
            'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 40)],
            'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false', 'class' => 'font-mono'],
        ]);
        $this->guard->addFields($builder);
    }
}
