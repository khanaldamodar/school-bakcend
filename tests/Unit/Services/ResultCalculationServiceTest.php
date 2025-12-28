<?php

namespace Tests\Unit\Services;

use App\Services\ResultCalculationService;
use App\Models\Admin\ResultSetting;
use App\Models\Admin\AcademicYear;
use App\Models\Admin\Term;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ResultCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResultCalculationService();
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(ResultCalculationService::class, $this->service);
    }

    /** @test */
    public function it_validates_result_setting_exists()
    {
        // Create a result setting
        ResultSetting::factory()->create();

        $isValid = $this->service->validateResultSetting();
        
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_validates_result_setting_not_exists()
    {
        $isValid = $this->service->validateResultSetting();
        
        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_gets_result_setting_for_academic_year()
    {
        $academicYear = AcademicYear::factory()->create();
        $resultSetting = ResultSetting::factory()->create([
            'academic_year_id' => $academicYear->id
        ]);

        $retrievedSetting = $this->service->getResultSetting($academicYear->id);
        
        $this->assertEquals($resultSetting->id, $retrievedSetting->id);
    }

    /** @test */
    public function it_throws_exception_when_no_result_setting_exists()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Result Setting is not configured');
        
        $this->service->getResultSetting();
    }

    /** @test */
    public function it_gets_current_academic_year()
    {
        $academicYear = AcademicYear::factory()->current()->create();
        
        $currentYear = $this->service->getCurrentAcademicYear();
        
        $this->assertEquals($academicYear->id, $currentYear->id);
    }

    /** @test */
    public function it_returns_null_when_no_current_academic_year()
    {
        $currentYear = $this->service->getCurrentAcademicYear();
        
        $this->assertNull($currentYear);
    }

    /** @test */
    public function it_checks_if_activities_can_be_added_with_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => true
        ]);

        $canAdd = $this->service->canAddActivities(1, $resultSetting);
        
        $this->assertTrue($canAdd);
    }

    /** @test */
    public function it_checks_if_activities_can_be_added_without_evaluation_per_term_for_last_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => false
        ]);
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $canAdd = $this->service->canAddActivities($term2->id, $resultSetting);
        
        $this->assertTrue($canAdd);
    }

    /** @test */
    public function it_checks_if_activities_cannot_be_added_without_evaluation_per_term_for_non_last_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => false
        ]);
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $canAdd = $this->service->canAddActivities($term1->id, $resultSetting);
        
        $this->assertFalse($canAdd);
    }

    /** @test */
    public function it_identifies_last_term_correctly()
    {
        $resultSetting = ResultSetting::factory()->create();
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);
        $term3 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 3]);

        $isLast = $this->service->isLastTerm($term3->id, $resultSetting);
        
        $this->assertTrue($isLast);
    }

    /** @test */
    public function it_identifies_non_last_term_correctly()
    {
        $resultSetting = ResultSetting::factory()->create();
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $isLast = $this->service->isLastTerm($term1->id, $resultSetting);
        
        $this->assertFalse($isLast);
    }

    /** @test */
    public function it_returns_false_for_last_term_when_no_terms_exist()
    {
        $resultSetting = ResultSetting::factory()->create();

        $isLast = $this->service->isLastTerm(1, $resultSetting);
        
        $this->assertFalse($isLast);
    }

    /** @test */
    public function it_includes_practical_with_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => true
        ]);

        $shouldInclude = $this->service->shouldIncludePractical(1, $resultSetting);
        
        $this->assertTrue($shouldInclude);
    }

    /** @test */
    public function it_includes_practical_only_in_last_term_without_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => false
        ]);
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $shouldInclude = $this->service->shouldIncludePractical($term2->id, $resultSetting);
        
        $this->assertTrue($shouldInclude);
    }

    /** @test */
    public function it_excludes_practical_in_non_last_term_without_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => false
        ]);
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $shouldInclude = $this->service->shouldIncludePractical($term1->id, $resultSetting);
        
        $this->assertFalse($shouldInclude);
    }

    /** @test */
    public function it_includes_activities_with_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => true
        ]);

        $shouldInclude = $this->service->shouldIncludeActivities(1, $resultSetting);
        
        $this->assertTrue($shouldInclude);
    }

    /** @test */
    public function it_includes_activities_only_in_last_term_without_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => false
        ]);
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $shouldInclude = $this->service->shouldIncludeActivities($term2->id, $resultSetting);
        
        $this->assertTrue($shouldInclude);
    }

    /** @test */
    public function it_excludes_activities_in_non_last_term_without_evaluation_per_term()
    {
        $resultSetting = ResultSetting::factory()->create([
            'evaluation_per_term' => false
        ]);
        
        // Create terms
        $term1 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 1]);
        $term2 = Term::factory()->create(['result_setting_id' => $resultSetting->id, 'order' => 2]);

        $shouldInclude = $this->service->shouldIncludeActivities($term1->id, $resultSetting);
        
        $this->assertFalse($shouldInclude);
    }

    /** @test */
    public function it_calculates_percentage_correctly()
    {
        $obtained = 75;
        $total = 100;
        
        $percentage = $this->service->calculatePercentage($obtained, $total);
        
        $this->assertEquals(75.0, $percentage);
    }

    /** @test */
    public function it_calculates_percentage_with_zero_total()
    {
        $obtained = 75;
        $total = 0;
        
        $percentage = $this->service->calculatePercentage($obtained, $total);
        
        $this->assertEquals(0.0, $percentage);
    }

    /** @test */
    public function it_calculates_gpa_correctly()
    {
        $obtained = 85;
        $total = 100;
        
        $gpa = $this->service->calculateGPA($obtained, $total);
        
        $this->assertEquals(3.6, $gpa);
    }

    /** @test */
    public function it_calculates_grade_from_percentage_correctly()
    {
        $percentage = 85;
        
        $grade = $this->service->getGradeFromPercentage($percentage);
        
        $this->assertEquals('A', $grade);
    }

    /** @test */
    public function it_calculates_grade_from_gpa_correctly()
    {
        $gpa = 3.6;
        
        $grade = $this->service->getGradeFromGPA($gpa);
        
        $this->assertEquals('A+', $grade);
    }

    /** @test */
    public function it_calculates_division_correctly()
    {
        $percentage = 75;
        
        $division = $this->service->getDivisionFromPercentage($percentage);
        
        $this->assertEquals('First Division', $division);
    }

    /** @test */
    public function it_validates_nepal_passing_criteria_correctly()
    {
        $theoryObtained = 40;
        $theoryTotal = 100;
        $practicalObtained = 20;
        $practicalTotal = 50;
        
        $isPassed = $this->service->validateNepalPassingCriteria(
            $theoryObtained, 
            $theoryTotal, 
            $practicalObtained, 
            $practicalTotal
        );
        
        $this->assertTrue($isPassed);
    }

    /** @test */
    public function it_validates_nepal_passing_criteria_failure_correctly()
    {
        $theoryObtained = 30;
        $theoryTotal = 100;
        $practicalObtained = 20;
        $practicalTotal = 50;
        
        $isPassed = $this->service->validateNepalPassingCriteria(
            $theoryObtained, 
            $theoryTotal, 
            $practicalObtained, 
            $practicalTotal
        );
        
        $this->assertFalse($isPassed);
    }
}