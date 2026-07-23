<?php

use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Support\EligibilityClassifier;

/**
 * The pure eligibility Classifier, unit-tested directly (spec §Testing Decisions — the
 * CreditHours / MeetingTime precedent). It maps a (student degree, candidate course)
 * pair to exactly one of the five Enrollment Classifications, or to `null` when the
 * course is not eligible for the Student at all. The whole degree/level/research-type
 * matrix and the internal-vs-external boundary live here, below the HTTP seam, so the
 * eligible-list endpoint (ticket 04) and the mint (ticket 06) trust one authority.
 *
 * The classifier is pure: the caller resolves the Catalog facts (own-curriculum
 * Category, whether the course sits in another active graduate Program's Curriculum)
 * and hands them in — the classifier touches no database.
 */
beforeEach(function (): void {
    $this->classifier = new EligibilityClassifier;
});

it('classifies an own-curriculum Preparatory course as Preparatory regardless of level', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Foundation,
        researchType: null,
        ownCategory: CourseCategory::Preparatory,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBe(EnrollmentClassification::Preparatory);
});

it('classifies an own-curriculum Program Core course as Core', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::PhD,
        courseLevel: CourseLevel::Doctoral,
        researchType: null,
        ownCategory: CourseCategory::ProgramCore,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBe(EnrollmentClassification::Core);
});

it('classifies an own Research Elective at the MSc elective level (500) as Internal Elective', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Master,
        researchType: null,
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBe(EnrollmentClassification::InternalElective);
});

it('classifies an own Research Elective at the PhD elective level (600) as Internal Elective', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::PhD,
        courseLevel: CourseLevel::Doctoral,
        researchType: null,
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBe(EnrollmentClassification::InternalElective);
});

it('does not classify an own Research Elective at the other degree\'s elective level', function () {
    // A 600-level course is the PhD elective level, not the MSc one — so for an MSc
    // student it is neither an Internal Elective nor (lacking a research type) anything.
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Doctoral,
        researchType: null,
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBeNull();
});

it('classifies an own level-700 PBL Research Elective as the Research Course for an MSc', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Advanced,
        researchType: 'PBL',
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBe(EnrollmentClassification::ResearchCourse);
});

it('classifies an own level-700 Seminar Research Elective as the Research Course for a PhD', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::PhD,
        courseLevel: CourseLevel::Advanced,
        researchType: 'Seminar',
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBe(EnrollmentClassification::ResearchCourse);
});

it('does not match a level-700 Research Course whose research type is for the other degree', function () {
    // Seminar is the PhD research type; an MSc's Research Course slot only matches PBL.
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Advanced,
        researchType: 'Seminar',
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBeNull();
});

it('does not match a level-700 own Research Elective that carries no research type', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::PhD,
        courseLevel: CourseLevel::Advanced,
        researchType: null,
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBeNull();
});

it('classifies a course outside the curriculum but in another active graduate program as External Elective (MSc, 500)', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Master,
        researchType: null,
        ownCategory: null,
        inOtherActiveGraduateCurriculum: true,
    );

    expect($result)->toBe(EnrollmentClassification::ExternalElective);
});

it('classifies a PhD external elective at level 600', function () {
    $result = $this->classifier->classify(
        degree: DegreeLevel::PhD,
        courseLevel: CourseLevel::Doctoral,
        researchType: null,
        ownCategory: null,
        inOtherActiveGraduateCurriculum: true,
    );

    expect($result)->toBe(EnrollmentClassification::ExternalElective);
});

it('does not classify an external candidate at the wrong level for the degree', function () {
    // 600 is the PhD elective level; for an MSc an external must be at 500.
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Doctoral,
        researchType: null,
        ownCategory: null,
        inOtherActiveGraduateCurriculum: true,
    );

    expect($result)->toBeNull();
});

it('does not classify an external-level course that is in no other active graduate curriculum', function () {
    // Right level, but the course sits in no other program's curriculum — not eligible.
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Master,
        researchType: null,
        ownCategory: null,
        inOtherActiveGraduateCurriculum: false,
    );

    expect($result)->toBeNull();
});

it('never treats a level-700 course outside the curriculum as an external elective', function () {
    // External electives are elective-level (500/600); a 700 research course is only
    // reachable through the student's own curriculum.
    $result = $this->classifier->classify(
        degree: DegreeLevel::PhD,
        courseLevel: CourseLevel::Advanced,
        researchType: 'Seminar',
        ownCategory: null,
        inOtherActiveGraduateCurriculum: true,
    );

    expect($result)->toBeNull();
});

it('prefers the own-curriculum classification even when the course also sits in another program', function () {
    // Being in another program's curriculum does not demote an own-curriculum course to
    // external — the own placement wins.
    $result = $this->classifier->classify(
        degree: DegreeLevel::MSc,
        courseLevel: CourseLevel::Master,
        researchType: null,
        ownCategory: CourseCategory::ResearchElective,
        inOtherActiveGraduateCurriculum: true,
    );

    expect($result)->toBe(EnrollmentClassification::InternalElective);
});
