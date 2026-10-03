<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MailAccount;
use App\Entity\MailRule;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\MailRuleField;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MailRule>
 */
final class MailRuleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Organization $organization */
        $organization = $options['organization'];

        $builder
            ->add('name', TextType::class, ['label' => 'mail_rule.name'])
            ->add('mailAccount', EntityType::class, [
                'label' => 'mail_rule.account',
                'class' => MailAccount::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail_rule.all_accounts',
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('a')
                    ->andWhere('a.organization = :org')->setParameter('org', $organization)->orderBy('a.name'),
            ])
            ->add('field', EnumType::class, [
                'label' => 'mail_rule.field.label',
                'class' => MailRuleField::class,
                'choice_label' => static fn (MailRuleField $f): string => $f->label(),
            ])
            ->add('needle', TextType::class, ['label' => 'mail_rule.needle', 'help' => 'mail_rule.needle_help'])
            ->add('project', EntityType::class, [
                'label' => 'mail_rule.project',
                'class' => Project::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'mail.project.none',
                'query_builder' => static fn (EntityRepository $r): QueryBuilder => $r->createQueryBuilder('p')
                    ->andWhere('p.organization = :org')->setParameter('org', $organization)->orderBy('p.name'),
            ])
            ->add('assignees', EntityType::class, [
                'label' => 'mail.assignees.label',
                'class' => User::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'choices' => $organization->getMembers(),
            ])
            ->add('markDone', CheckboxType::class, ['label' => 'mail_rule.mark_done', 'required' => false, 'help' => 'mail_rule.mark_done_help'])
            ->add('enabled', CheckboxType::class, ['label' => 'mail_rule.enabled', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MailRule::class]);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', Organization::class);
    }
}
