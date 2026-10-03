<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AgendaItem;
use App\Entity\StoredFile;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Agenda item; proposals by members only have title and description.
 *
 * @extends AbstractType<AgendaItem>
 */
final class AgendaItemFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'meeting.agenda.title'])
            ->add('description', TextareaType::class, ['label' => 'meeting.agenda.description', 'required' => false, 'attr' => ['rows' => 4]]);
        if ($options['proposal']) {
            return;
        }
        $builder
            ->add('responsible', EntityType::class, [
                'label' => 'meeting.agenda.responsible',
                'class' => User::class,
                'choices' => $options['users'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'meeting.agenda.responsible_none',
            ])
            ->add('durationMinutes', IntegerType::class, ['label' => 'meeting.agenda.duration', 'required' => false, 'attr' => ['min' => 1, 'max' => 600]])
            ->add('attachments', EntityType::class, [
                'label' => 'meeting.agenda.attachments',
                'class' => StoredFile::class,
                'choices' => $options['files'],
                'choice_label' => static fn (StoredFile $f): string => $f->getFolder()->getName().' / '.$f->getFilename(),
                'multiple' => true,
                'required' => false,
                'help' => 'meeting.agenda.attachments_help',
                'attr' => ['size' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AgendaItem::class,
            'users' => [],
            'files' => [],
            'proposal' => false,
        ]);
    }
}
