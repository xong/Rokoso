<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SurveyQuestion;
use App\Enum\SurveyQuestionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SurveyQuestion>
 */
final class SurveyQuestionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'survey.question.label', 'empty_data' => ''])
            ->add('type', EnumType::class, [
                'label' => 'survey.question.type',
                'class' => SurveyQuestionType::class,
                'choice_label' => static fn (SurveyQuestionType $t): string => 'survey.type.'.$t->value,
            ])
            ->add('optionsText', TextareaType::class, ['label' => 'survey.question.options', 'help' => 'survey.question.options_help', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('required', CheckboxType::class, ['label' => 'survey.question.required', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SurveyQuestion::class]);
    }
}
