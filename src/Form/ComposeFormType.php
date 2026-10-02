<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MailAccount;
use App\Entity\Project;
use App\Mail\ComposeData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<ComposeData>
 */
final class ComposeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', EntityType::class, [
                'label' => 'compose.from',
                'class' => MailAccount::class,
                'choices' => $options['accounts'],
                'choice_label' => static fn (MailAccount $a): string => \sprintf('%s <%s>', $a->getSenderName() ?? $a->getName(), $a->getEmailAddress()),
            ])
            ->add('to', TextType::class, ['label' => 'compose.to', 'empty_data' => '', 'help' => 'compose.addresses_help', 'attr' => ['autocomplete' => 'off']])
            ->add('cc', TextType::class, ['label' => 'compose.cc', 'empty_data' => '', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('bcc', TextType::class, ['label' => 'compose.bcc', 'empty_data' => '', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('subject', TextType::class, ['label' => 'compose.subject', 'empty_data' => '', 'required' => false])
            ->add('body', TextareaType::class, ['label' => 'compose.body', 'empty_data' => '', 'required' => false, 'attr' => ['rows' => 14, 'class' => 'font-mono']])
            ->add('files', FileType::class, [
                'label' => 'compose.attachments',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'help' => 'compose.attachments_help',
                'constraints' => [new Assert\All([new Assert\File(maxSize: '20M')])],
            ])
            ->add('project', EntityType::class, [
                'label' => 'nav.projects',
                'class' => Project::class,
                'choices' => $options['projects'],
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
            ]);

        if ($options['forward_attachments']) {
            $builder->add('keepAttachments', CheckboxType::class, ['label' => 'compose.keep_attachments', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ComposeData::class,
            'accounts' => [],
            'projects' => [],
            'forward_attachments' => false,
        ]);
        $resolver->setAllowedTypes('accounts', 'array');
        $resolver->setAllowedTypes('projects', 'array');
        $resolver->setAllowedTypes('forward_attachments', 'bool');
    }
}
