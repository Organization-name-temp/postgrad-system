<?php

namespace Modules\AcademicStanding\Public;

enum CreditHourCategory: string
{
    case Preparatory = 'Preparatory';
    case ProgramCore = 'ProgramCore';
    case ResearchElective = 'ResearchElective';
    case Total = 'Total';
}
