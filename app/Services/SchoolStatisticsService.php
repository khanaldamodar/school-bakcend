<?php

namespace App\Services;

use App\Models\Admin\AcademicYear;
use App\Models\Admin\FinalResult;
use App\Models\Admin\ParentModel;
use App\Models\Admin\SchoolClass;
use App\Models\Admin\Student;
use App\Models\Admin\StudentClassHistory;
use App\Models\Admin\Teacher;
use Illuminate\Support\Facades\DB;

class SchoolStatisticsService
{
    /**
     * Safe aggregates for the public website (no gender / per-class / exam breakdowns).
     */
    public function buildPublicOverview(): array
    {
        $currentYear = AcademicYear::where('is_current', true)->first();

        $exam = [
            'pass_percentage' => 0.0,
            'passed_students_count' => 0,
            'results_recorded_count' => 0,
        ];

        if ($currentYear) {
            $overall = $this->examOverall($currentYear->id);
            $exam['pass_percentage'] = $overall['pass_rate_pct'];
            $exam['passed_students_count'] = $overall['passed_count'];
            $exam['results_recorded_count'] = $overall['results_recorded_count'];
        }

        return [
            'current_academic_year' => $currentYear ? [
                'id' => $currentYear->id,
                'name' => $currentYear->name,
            ] : null,
            'summary' => [
                'total_students' => Student::query()->count(),
                'total_teachers' => Teacher::query()->count(),
                'total_classes' => SchoolClass::query()->count(),
            ],
            'academic_performance' => $exam,
        ];
    }

    public function build(AcademicYear $year, array $filters = []): array
    {
        $historyCount = StudentClassHistory::query()
            ->where('academic_year_id', $year->id)
            ->count();

        $useCurrentRoster = $historyCount === 0 && $year->is_current;

        if ($useCurrentRoster) {
            $enrollment = $this->enrollmentFromCurrentStudents($filters);
            $enrollmentSource = 'current_roster';
        } else {
            $enrollment = $this->enrollmentFromHistory($year->id, $filters);
            $enrollmentSource = 'student_class_history';
        }

        $classes = SchoolClass::query()
            ->orderBy('name')
            ->orderBy('section')
            ->get(['id', 'name', 'class_code', 'section', 'class_teacher_id']);

        $byClass = $this->mergeClassRows($classes, $enrollment['by_class']);
        $teacherCountByClass = $this->teacherCountByClass($classes);

        foreach ($byClass as &$row) {
            $row['teacher_count'] = $teacherCountByClass[$row['class_id']] ?? 0;
        }
        unset($row);

        return [
            'meta' => $this->meta($year),
            'enrollment_source' => $enrollmentSource,
            'summary' => $this->summary($enrollment, $classes->count()),
            'teachers' => $this->teachersSummary(),
            'by_class' => array_values($byClass),
            'exam_overall' => $this->examOverall($year->id, $filters),
            'exam_by_gender' => $this->examByGender($year->id, $filters),
            'exam_by_class' => $this->examByClass($year->id, $filters),
            'teacher_mapping' => $this->getTeacherClassMapping($year),
        ];
    }

    private function meta(AcademicYear $year): array
    {
        return [
            'academic_year_id' => $year->id,
            'academic_year_name' => $year->name,
            'start_date' => $year->start_date?->toDateString(),
            'end_date' => $year->end_date?->toDateString(),
            'is_current' => (bool) $year->is_current,
        ];
    }

    /**
     * @return array{by_class: array<int, array<string, int>>, distinct_students: int}
     */
    private function enrollmentFromHistory(int $academicYearId, array $filters = []): array
    {
        $rows = DB::table('student_class_histories as sch')
            ->join('students as s', 's.id', '=', 'sch.student_id')
            ->where('sch.academic_year_id', $academicYearId)
            ->where('s.is_deleted', false)
            ->when(isset($filters['class_id']), fn($q) => $q->where('sch.class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('s.gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('s.is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('s.is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('s.ethnicity', $filters['ethnicity']))
            ->selectRaw('sch.class_id, s.gender, COUNT(DISTINCT sch.student_id) as c')
            ->groupBy('sch.class_id', 's.gender')
            ->get();

        $byClass = $this->pivotGenderByClass($rows);

        $distinctStudents = (int) DB::table('student_class_histories as sch')
            ->join('students as s', 's.id', '=', 'sch.student_id')
            ->where('sch.academic_year_id', $academicYearId)
            ->where('s.is_deleted', false)
            ->when(isset($filters['class_id']), fn($q) => $q->where('sch.class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('s.gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('s.is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('s.is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('s.ethnicity', $filters['ethnicity']))
            ->count(DB::raw('DISTINCT sch.student_id'));

        return ['by_class' => $byClass, 'distinct_students' => $distinctStudents];
    }

    /**
     * @return array{by_class: array<int, array<string, int>>, distinct_students: int}
     */
    private function enrollmentFromCurrentStudents(array $filters = []): array
    {
        $rows = DB::table('students as s')
            ->where('s.is_deleted', false)
            ->whereNotNull('s.class_id')
            ->when(isset($filters['class_id']), fn($q) => $q->where('s.class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('s.gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('s.is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('s.is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('s.ethnicity', $filters['ethnicity']))
            ->selectRaw('s.class_id, s.gender, COUNT(*) as c')
            ->groupBy('s.class_id', 's.gender')
            ->get();

        $byClass = $this->pivotGenderByClass($rows);

        $distinctStudents = (int) Student::query()->whereNotNull('class_id')
            ->when(isset($filters['class_id']), fn($q) => $q->where('class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('ethnicity', $filters['ethnicity']))
            ->count();

        return ['by_class' => $byClass, 'distinct_students' => $distinctStudents];
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows each has class_id, gender, c
     * @return array<int, array<string, int>>
     */
    private function pivotGenderByClass($rows): array
    {
        $byClass = [];
        foreach ($rows as $r) {
            $classId = (int) $r->class_id;
            $bucket = $this->normalizeGender($r->gender ?? null);
            if (!isset($byClass[$classId])) {
                $byClass[$classId] = [
                    'male_count' => 0,
                    'female_count' => 0,
                    'other_count' => 0,
                    'unknown_gender_count' => 0,
                ];
            }
            $key = match ($bucket) {
                'male' => 'male_count',
                'female' => 'female_count',
                'other' => 'other_count',
                default => 'unknown_gender_count',
            };
            $byClass[$classId][$key] += (int) $r->c;
        }

        foreach ($byClass as $cid => $counts) {
            $byClass[$cid]['student_count'] = array_sum([
                $counts['male_count'],
                $counts['female_count'],
                $counts['other_count'],
                $counts['unknown_gender_count'],
            ]);
        }

        return $byClass;
    }

    private function normalizeGender(?string $gender): string
    {
        $g = strtolower(trim((string) $gender));
        return in_array($g, ['male', 'female', 'other'], true) ? $g : 'unknown';
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Admin\SchoolClass> $classes
     * @param array<int, array<string, int>> $enrollmentByClass
     * @return array<int, array<string, mixed>>
     */
    private function mergeClassRows($classes, array $enrollmentByClass): array
    {
        $out = [];
        foreach ($classes as $class) {
            $e = $enrollmentByClass[$class->id] ?? null;
            $out[$class->id] = [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'class_code' => $class->class_code,
                'section' => $class->section,
                'student_count' => $e['student_count'] ?? 0,
                'male_count' => $e['male_count'] ?? 0,
                'female_count' => $e['female_count'] ?? 0,
                'other_count' => $e['other_count'] ?? 0,
                'unknown_gender_count' => $e['unknown_gender_count'] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\Admin\SchoolClass> $classes
     * @return array<int, int>
     */
    private function teacherCountByClass($classes): array
    {
        $pivot = DB::table('class_subject_teacher')
            ->select('class_id', 'teacher_id')
            ->get()
            ->groupBy('class_id');

        $counts = [];
        foreach ($classes as $class) {
            $ids = collect($pivot->get($class->id, collect()))->pluck('teacher_id')->unique()->filter();
            if ($class->class_teacher_id) {
                $ids = $ids->push($class->class_teacher_id);
            }
            $counts[$class->id] = $ids->unique()->count();
        }

        return $counts;
    }

    private function summary(array $enrollment, int $totalClassDefinitions): array
    {
        $classesWithStudents = collect($enrollment['by_class'])
            ->filter(fn ($row) => ($row['student_count'] ?? 0) > 0)
            ->count();

        return [
            'total_students' => $enrollment['distinct_students'],
            'total_teachers' => Teacher::query()->count(),
            'total_classes' => $totalClassDefinitions,
            'classes_with_students' => $classesWithStudents,
            'total_parents' => ParentModel::query()->count(),
        ];
    }

    private function teachersSummary(): array
    {
        $row = DB::table('teachers')
            ->where('is_deleted', false)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(gender) = 'male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN LOWER(gender) = 'female' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN LOWER(gender) = 'other' THEN 1 ELSE 0 END) as other,
                SUM(CASE WHEN gender IS NULL OR LOWER(gender) NOT IN ('male', 'female', 'other') THEN 1 ELSE 0 END) as unknown
            ")
            ->first();

        return [
            'total_teachers' => (int) ($row->total ?? 0),
            'male_count' => (int) ($row->male ?? 0),
            'female_count' => (int) ($row->female ?? 0),
            'other_count' => (int) ($row->other ?? 0),
            'unknown_gender_count' => (int) ($row->unknown ?? 0),
        ];
    }

    private function examOverall(int $academicYearId, array $filters = []): array
    {
        $base = FinalResult::query()
            ->when(isset($filters['subject_id']), fn($q) => $q->where('final_results.subject_id', $filters['subject_id']), fn($q) => $q->overall())
            ->forAcademicYear($academicYearId)
            ->join('students as s', 's.id', '=', 'final_results.student_id')
            ->where('s.is_deleted', false)
            ->when(isset($filters['class_id']), fn($q) => $q->where('final_results.class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('s.gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('s.is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('s.is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('s.ethnicity', $filters['ethnicity']));

        $passed = (clone $base)->where('final_results.is_passed', true)->count();
        $failed = (clone $base)->where('final_results.is_passed', false)->count();
        $total = $passed + $failed;

        return [
            'results_recorded_count' => $total,
            'passed_count' => $passed,
            'failed_count' => $failed,
            'pass_rate_pct' => $total > 0 ? round(100 * $passed / $total, 2) : 0.0,
            'fail_rate_pct' => $total > 0 ? round(100 * $failed / $total, 2) : 0.0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function examByGender(int $academicYearId, array $filters = []): array
    {
        $genderExpr = "CASE WHEN LOWER(s.gender) = 'male' THEN 'male' WHEN LOWER(s.gender) = 'female' THEN 'female' WHEN LOWER(s.gender) = 'other' THEN 'other' ELSE 'unknown' END";

        $rows = FinalResult::query()
            ->when(isset($filters['subject_id']), fn($q) => $q->where('final_results.subject_id', $filters['subject_id']), fn($q) => $q->overall())
            ->forAcademicYear($academicYearId)
            ->join('students as s', 's.id', '=', 'final_results.student_id')
            ->where('s.is_deleted', false)
            ->when(isset($filters['class_id']), fn($q) => $q->where('final_results.class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('s.gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('s.is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('s.is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('s.ethnicity', $filters['ethnicity']))
            ->selectRaw("{$genderExpr} as gender_bucket, "
                .'SUM(CASE WHEN final_results.is_passed = 1 THEN 1 ELSE 0 END) as passed_count, '
                .'SUM(CASE WHEN final_results.is_passed = 0 THEN 1 ELSE 0 END) as failed_count')
            ->groupByRaw($genderExpr)
            ->get();

        $order = ['male', 'female', 'other', 'unknown'];
        $out = [];
        foreach ($order as $g) {
            $hit = $rows->firstWhere('gender_bucket', $g);
            $p = (int) ($hit->passed_count ?? 0);
            $f = (int) ($hit->failed_count ?? 0);
            $t = $p + $f;
            $out[] = [
                'gender' => $g,
                'passed_count' => $p,
                'failed_count' => $f,
                'pass_rate_pct' => $t > 0 ? round(100 * $p / $t, 2) : 0.0,
                'fail_rate_pct' => $t > 0 ? round(100 * $f / $t, 2) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function examByClass(int $academicYearId, array $filters = []): array
    {
        $rows = FinalResult::query()
            ->when(isset($filters['subject_id']), fn($q) => $q->where('final_results.subject_id', $filters['subject_id']), fn($q) => $q->overall())
            ->forAcademicYear($academicYearId)
            ->join('students as s', 's.id', '=', 'final_results.student_id')
            ->where('s.is_deleted', false)
            ->when(isset($filters['class_id']), fn($q) => $q->where('final_results.class_id', $filters['class_id']))
            ->when(isset($filters['gender']), fn($q) => $q->where('s.gender', $filters['gender']))
            ->when(isset($filters['is_tribe']), fn($q) => $q->where('s.is_tribe', $filters['is_tribe']))
            ->when(isset($filters['is_disabled']), fn($q) => $q->where('s.is_disabled', $filters['is_disabled']))
            ->when(isset($filters['ethnicity']), fn($q) => $q->where('s.ethnicity', $filters['ethnicity']))
            ->join('classes as c', 'c.id', '=', 'final_results.class_id')
            ->selectRaw('
                final_results.class_id,
                c.name as class_name,
                c.class_code,
                c.section,
                SUM(CASE WHEN final_results.is_passed = 1 THEN 1 ELSE 0 END) as passed_count,
                SUM(CASE WHEN final_results.is_passed = 0 THEN 1 ELSE 0 END) as failed_count
            ')
            ->groupBy('final_results.class_id', 'c.name', 'c.class_code', 'c.section')
            ->orderBy('c.name')
            ->orderBy('c.section')
            ->get();

        $list = [];
        foreach ($rows as $r) {
            $p = (int) $r->passed_count;
            $f = (int) $r->failed_count;
            $t = $p + $f;
            $list[] = [
                'class_id' => (int) $r->class_id,
                'class_name' => $r->class_name,
                'class_code' => $r->class_code,
                'section' => $r->section,
                'passed_count' => $p,
                'failed_count' => $f,
                'pass_rate_pct' => $t > 0 ? round(100 * $p / $t, 2) : 0.0,
                'fail_rate_pct' => $t > 0 ? round(100 * $f / $t, 2) : 0.0,
            ];
        }

        return $list;
    }

    public function getTeacherClassMapping(AcademicYear $year): array
    {
        return SchoolClass::query()
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(function ($class) {
                $subjectTeachers = DB::table('class_subject_teacher')
                    ->join('teachers', 'teachers.id', '=', 'class_subject_teacher.teacher_id')
                    ->join('subjects', 'subjects.id', '=', 'class_subject_teacher.subject_id')
                    ->where('class_subject_teacher.class_id', $class->id)
                    ->where('teachers.is_deleted', false)
                    ->select('teachers.name as teacher_name', 'subjects.name as subject_name')
                    ->get();

                return [
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'class_code' => $class->class_code,
                    'section' => $class->section,
                    'subject_teachers' => $subjectTeachers->map(fn($st) => [
                        'name' => $st->teacher_name,
                        'subject' => $st->subject_name
                    ])
                ];
            })->toArray();
    }


    public function calculateTrends(array $current, array $previous): array
    {
        $metrics = [
            'total_students' => ['path' => 'summary.total_students'],
            'total_teachers' => ['path' => 'summary.total_teachers'],
            'passed_count' => ['path' => 'exam_overall.passed_count'],
            'pass_rate' => ['path' => 'exam_overall.pass_rate_pct'],
        ];

        $trends = ['summary' => []];
        foreach ($metrics as $key => $config) {
            $currVal = data_get($current, $config['path'], 0);
            $prevVal = data_get($previous, $config['path'], 0);

            $diff = $currVal - $prevVal;
            $pct = $prevVal > 0 ? round(($diff / $prevVal) * 100, 2) : ($currVal > 0 ? 100 : 0);

            $trends['summary'][$key] = [
                'current' => $currVal,
                'previous' => $prevVal,
                'difference' => $diff,
                'percentage_change' => $pct,
                'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable'),
            ];
        }

        // Detailed Trends by Gender
        $trends['by_gender'] = $this->compareLists(
            $current['exam_by_gender'],
            $previous['exam_by_gender'],
            'gender',
            'pass_rate_pct'
        );

        // Detailed Trends by Class
        $trends['by_class'] = $this->compareLists(
            $current['exam_by_class'],
            $previous['exam_by_class'],
            'class_code',
            'pass_rate_pct'
        );

        return $trends;
    }

    private function compareLists(array $current, array $previous, string $keyField, string $valueField): array
    {
        $out = [];
        foreach ($current as $currItem) {
            $key = $currItem[$keyField];
            $prevItem = collect($previous)->firstWhere($keyField, $key);

            $currVal = $currItem[$valueField] ?? 0;
            $prevVal = $prevItem[$valueField] ?? 0;
            $diff = round($currVal - $prevVal, 2);

            $out[$key] = [
                'current' => $currVal,
                'previous' => $prevVal,
                'change' => $diff,
                'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'stable'),
            ];
        }
        return $out;
    }
}