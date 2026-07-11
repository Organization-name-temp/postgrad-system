<?php

use Tests\TestCase;

/*
| Bind Laravel's TestCase to the Feature suite so feature tests can drive the
| HTTP kernel ($this->getJson(...), etc.). Database-touching tests opt into
| RefreshDatabase per file (from ticket 04 on); Phase 0's tracer needs no DB, so
| it is not applied globally here.
*/

uses(TestCase::class)->in('Feature');
