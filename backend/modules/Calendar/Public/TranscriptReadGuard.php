<?php

namespace Modules\Calendar\Public;

/** Protects this owner's Transcript sources until the calling transaction ends. */
interface TranscriptReadGuard
{
    public function lock(): void;
}
