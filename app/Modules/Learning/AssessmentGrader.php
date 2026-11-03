<?php

namespace App\Modules\Learning;

use InvalidArgumentException;

final class AssessmentGrader
{
    /** @param array<int, array{id:string, options:array<int, array{key:string}>, correct_option:string}> $questions */
    public function grade(array $questions, array $answers, int $passingScore): array
    {
        $questionIds = array_column($questions, 'id');
        if (count($answers) !== count($questionIds) || array_diff(array_keys($answers), $questionIds) !== [] || array_diff($questionIds, array_keys($answers)) !== []) {
            throw new InvalidArgumentException('Answer exactly one option for every assessment question.');
        }

        $correct = 0;
        foreach ($questions as $question) {
            $selected = $answers[$question['id']];
            if (! in_array($selected, array_column($question['options'], 'key'), true)) {
                throw new InvalidArgumentException('The selected option is invalid.');
            }
            if (hash_equals($question['correct_option'], $selected)) {
                $correct++;
            }
        }

        $score = (int) round(($correct / max(1, count($questions))) * 100);

        return ['correct' => $correct, 'score' => $score, 'passed' => $score >= $passingScore];
    }
}
