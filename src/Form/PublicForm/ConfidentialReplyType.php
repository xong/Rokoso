<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Participation\PublicGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Further message of the anonymous person; the access code travels in a hidden field (never in the address).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ConfidentialReplyType extends AbstractType
{
    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', HiddenType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 40)]])
            ->add('message', TextareaType::class, ['label' => 'public.confidential.reply', 'attr' => ['rows' => 6], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20000)]]);
        $this->guard->addFields($builder);
    }
}
