<?php

namespace Tests\Unit;

use App\Modules\Learning\AssessmentGrader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssessmentGraderTest extends TestCase
{
    private function questions(): array
    {
        return [
            ['id' => 'q1', 'options' => [['key' => 'a'], ['key' => 'b']], 'correct_option' => 'b'],
            ['id' => 'q2', 'options' => [['key' => 'a'], ['key' => 'b']], 'correct_option' => 'a'],
        ];
    }

    #[Test]
    public function it_grades_correctly_and_applies_the_pass_mark(): void
    {
        $result = (new AssessmentGrader)->grade($this->questions(), ['q1' => 'b', 'q2' => 'a'], 70);

        $this->assertSame(['correct' => 2, 'score' => 100, 'passed' => true], $result);
    }

    #[Test]
    public function it_allows_an_unsuccessful_attempt_to_be_retried(): void
    {
        $result = (new AssessmentGrader)->grade($this->questions(), ['q1' => 'a', 'q2' => 'b'], 70);

        $this->assertSame(['correct' => 0, 'score' => 0, 'passed' => false], $result);
    }

    #[Test]
    public function it_rejects_missing_extra_and_invalid_answers(): void
    {
        $grader = new AssessmentGrader;
        foreach ([['q1' => 'b'], ['q1' => 'b', 'q2' => 'a', 'extra' => 'x'], ['q1' => 'z', 'q2' => 'a']] as $answers) {
            try {
                $grader->grade($this->questions(), $answers, 70);
                $this->fail('Invalid answer payload should be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
