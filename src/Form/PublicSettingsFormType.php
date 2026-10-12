<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MailAccount;
use App\Entity\PublicSettings;
use App\Enum\PublicDesign;
use App\Enum\Theme;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PublicSettings>
 */
final class PublicSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $flag = static fn (string $key): array => ['label' => 'public.settings.'.$key, 'help' => 'public.settings.'.$key.'_help', 'required' => false];
        $builder
            ->add('slug', TextType::class, ['label' => 'public.settings.slug', 'help' => 'public.settings.slug_help', 'required' => false, 'attr' => ['pattern' => '[a-z0-9\-]+', 'autocomplete' => 'off']])
            ->add('infoEnabled', CheckboxType::class, $flag('info'))
            ->add('intro', TextareaType::class, ['label' => 'public.settings.intro', 'help' => 'public.settings.intro_help', 'required' => false, 'attr' => ['rows' => 6]])
            ->add('contactEnabled', CheckboxType::class, $flag('contact'))
            ->add('contactAccount', EntityType::class, [
                'label' => 'public.settings.account',
                'class' => MailAccount::class,
                'choices' => $options['accounts'],
                'choice_label' => static fn (MailAccount $a): string => $a->getName().' <'.$a->getEmailAddress().'>',
                'required' => false,
                'placeholder' => 'public.settings.account_none',
            ])
            ->add('topicsEnabled', CheckboxType::class, $flag('topics'))
            ->add('surveysEnabled', CheckboxType::class, $flag('surveys'))
            ->add('eventsEnabled', CheckboxType::class, $flag('events'))
            ->add('subscribeEnabled', CheckboxType::class, $flag('subscribe'))
            ->add('confidentialEnabled', CheckboxType::class, $flag('confidential'))
            ->add('design', EnumType::class, [
                'label' => 'public.settings.design',
                'class' => PublicDesign::class,
                'expanded' => true,
                'choice_label' => static fn (PublicDesign $d): string => $d->label(),
            ])
            ->add('scheme', EnumType::class, [
                'label' => 'public.settings.scheme',
                'class' => Theme::class,
                'expanded' => true,
                'choices' => [Theme::Light, Theme::Dark, Theme::System],
                'choice_label' => static fn (Theme $t): string => 'public.scheme.'.$t->value,
            ])
            ->add('accentColor', ChoiceType::class, [
                'label' => 'public.settings.accent',
                'expanded' => true,
                'required' => false,
                'placeholder' => 'public.accent.organization',
                'choices' => array_combine(array_map(static fn (string $name): string => 'public.accent.'.$name, array_keys(PublicSettings::ACCENTS)), PublicSettings::ACCENTS),
            ])
            ->add('privacyNotice', TextareaType::class, ['label' => 'public.settings.privacy', 'help' => 'public.settings.privacy_help', 'required' => false, 'attr' => ['rows' => 5]])
            ->add('spamInvisible', CheckboxType::class, $flag('spam_invisible'))
            ->add('spamMinSeconds', IntegerType::class, ['label' => 'public.settings.spam_min_seconds', 'attr' => ['min' => 0, 'max' => 120]])
            ->add('spamMaxPerHour', IntegerType::class, ['label' => 'public.settings.spam_max_per_hour', 'attr' => ['min' => 1, 'max' => 1000]])
            ->add('spamConfirmEmail', CheckboxType::class, $flag('spam_confirm'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PublicSettings::class, 'accounts' => []]);
        $resolver->setAllowedTypes('accounts', 'array');
    }
}
