<?php

declare(strict_types=1);

namespace App\Form\PublicForm;

use App\Entity\Survey;
use App\Enum\SurveyQuestionType;
use App\Participation\PublicGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Answer form built from the questions of a survey; fields are named q{id}.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class SurveyAnswerType extends AbstractType
{
    public function __construct(private readonly PublicGuard $guard)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Survey $survey */
        $survey = $options['survey'];
        foreach ($survey->getQuestions() as $question) {
            $base = ['label' => $question->getLabel(), 'translation_domain' => false, 'required' => $question->isRequired()];
            $choices = array_combine($question->getOptions(), $question->getOptions());
            [$type, $config] = match ($question->getType()) {
                SurveyQuestionType::Single => [ChoiceType::class, ['choices' => $choices, 'expanded' => true, 'placeholder' => false,
                    'constraints' => $question->isRequired() ? [new Assert\NotBlank(message: 'survey.answer_required')] : []]],
                SurveyQuestionType::Multiple => [ChoiceType::class, ['choices' => $choices, 'expanded' => true, 'multiple' => true,
                    'constraints' => $question->isRequired() ? [new Assert\Count(min: 1, minMessage: 'survey.answer_required')] : []]],
                SurveyQuestionType::Scale => [ChoiceType::class, ['choices' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5], 'expanded' => true, 'placeholder' => false,
                    'attr' => ['class' => 'flex flex-wrap gap-4'],
                    'constraints' => $question->isRequired() ? [new Assert\NotBlank(message: 'survey.answer_required')] : []]],
                SurveyQuestionType::Text => [TextareaType::class, ['attr' => ['rows' => 4],
                    'constraints' => array_merge([new Assert\Length(max: 5000)], $question->isRequired() ? [new Assert\NotBlank(message: 'survey.answer_required')] : [])]],
            };
            $builder->add('q'.$question->getId(), $type, $base + $config);
        }
        if (!$survey->isAnonymous()) {
            $builder
                ->add('name', TextType::class, ['label' => 'public.field.name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)], 'attr' => ['autocomplete' => 'name']])
                ->add('email', EmailType::class, ['label' => 'public.field.email', 'required' => false, 'constraints' => [new Assert\Email(), new Assert\Length(max: 180)], 'attr' => ['autocomplete' => 'email']])
                ->add('consent', CheckboxType::class, ['label' => 'public.field.consent', 'constraints' => [new Assert\IsTrue(message: 'public.consent_required')]]);
        }
        $this->guard->addFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('survey');
        $resolver->setAllowedTypes('survey', Survey::class);
    }
}
