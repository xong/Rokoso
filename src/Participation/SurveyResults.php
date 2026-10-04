<?php

declare(strict_types=1);

namespace App\Participation;

use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Enum\SurveyQuestionType;

/**
 * Evaluation of a survey: counts per option, scale average, free texts, CSV export.
 */
final class SurveyResults
{
    /**
     * @return array<int, array{counts: array<int|string, int>, answered: int, average: ?float, texts: list<string>}> keyed by question id
     */
    public function summarize(Survey $survey): array
    {
        $result = [];
        foreach ($survey->getQuestions() as $question) {
            $counts = [];
            if ($question->getType()->hasOptions()) {
                $counts = array_fill_keys($question->getOptions(), 0);
            } elseif (SurveyQuestionType::Scale === $question->getType()) {
                $counts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
            }
            $answered = 0;
            $sum = 0;
            $texts = [];
            foreach ($survey->getResponses() as $response) {
                $answer = $response->getAnswer($question);
                if (null === $answer || '' === $answer || [] === $answer) {
                    continue;
                }
                ++$answered;
                foreach ((array) $answer as $value) {
                    $value = (string) $value;
                    if (SurveyQuestionType::Text === $question->getType()) {
                        $texts[] = $value;
                    } elseif (\array_key_exists($value, $counts)) {
                        ++$counts[$value];
                        $sum += (int) $value;
                    }
                }
            }
            $result[(int) $question->getId()] = [
                'counts' => array_map(intval(...), $counts),
                'answered' => $answered,
                'average' => SurveyQuestionType::Scale === $question->getType() && $answered > 0 ? round($sum / $answered, 1) : null,
                'texts' => $texts,
            ];
        }

        return $result;
    }

    /** CSV for spreadsheets: semicolon separated, UTF-8 with BOM */
    public function csv(Survey $survey, string $dateLabel, string $nameLabel, string $emailLabel): string
    {
        $out = fopen('php://temp', 'r+');
        \assert(false !== $out);
        $header = [$dateLabel];
        if (!$survey->isAnonymous()) {
            $header[] = $nameLabel;
            $header[] = $emailLabel;
        }
        foreach ($survey->getQuestions() as $question) {
            $header[] = $question->getLabel();
        }
        fputcsv($out, $header, ';', '"', '');
        foreach ($survey->getResponses() as $response) {
            fputcsv($out, $this->row($survey, $response), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return "\u{FEFF}".$csv;
    }

    /**
     * @return list<string>
     */
    private function row(Survey $survey, SurveyResponse $response): array
    {
        $row = [$response->getCreatedAt()->format('d.m.Y H:i')];
        if (!$survey->isAnonymous()) {
            $row[] = (string) $response->getName();
            $row[] = (string) $response->getEmail();
        }
        foreach ($survey->getQuestions() as $question) {
            $answer = $response->getAnswer($question);
            $row[] = \is_array($answer) ? implode(', ', $answer) : (string) $answer;
        }

        return $row;
    }
}
