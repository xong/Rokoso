<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MailAccount;
use App\Enum\MailEncryption;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Passwords are unmapped plain fields; the controller encrypts them. Empty = keep current.
 *
 * @extends AbstractType<MailAccount>
 */
final class MailAccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $encryption = [
            'class' => MailEncryption::class,
            'choice_label' => static fn (MailEncryption $e): string => $e->label(),
        ];
        $passwordHelp = $options['is_new'] ? null : 'mail_account.password_keep';

        $builder
            ->add('name', TextType::class, ['label' => 'mail_account.name', 'help' => 'mail_account.name_help'])
            ->add('emailAddress', EmailType::class, ['label' => 'mail_account.email_address'])
            ->add('senderName', TextType::class, ['label' => 'mail_account.sender_name', 'required' => false])
            ->add('imapHost', TextType::class, ['label' => 'mail_account.host', 'attr' => ['placeholder' => 'imap.example.org']])
            ->add('imapPort', IntegerType::class, ['label' => 'mail_account.port'])
            ->add('imapEncryption', EnumType::class, ['label' => 'mail_account.encryption.label'] + $encryption)
            ->add('imapUsername', TextType::class, ['label' => 'mail_account.username', 'attr' => ['autocomplete' => 'off']])
            ->add('imapPasswordPlain', PasswordType::class, [
                'label' => 'mail_account.password',
                'mapped' => false,
                'required' => $options['is_new'],
                'help' => $passwordHelp,
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('inboxFolder', TextType::class, ['label' => 'mail_account.inbox_folder'])
            ->add('importDays', IntegerType::class, ['label' => 'mail_account.import_days', 'help' => 'mail_account.import_days_help'])
            ->add('smtpHost', TextType::class, ['label' => 'mail_account.host', 'attr' => ['placeholder' => 'smtp.example.org']])
            ->add('smtpPort', IntegerType::class, ['label' => 'mail_account.port'])
            ->add('smtpEncryption', EnumType::class, ['label' => 'mail_account.encryption.label'] + $encryption)
            ->add('smtpUsername', TextType::class, [
                'label' => 'mail_account.username',
                'required' => false,
                'help' => 'mail_account.smtp_same_help',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('smtpPasswordPlain', PasswordType::class, [
                'label' => 'mail_account.password',
                'mapped' => false,
                'required' => false,
                'help' => $passwordHelp,
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('enabled', CheckboxType::class, ['label' => 'mail_account.enabled', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MailAccount::class, 'is_new' => false]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
