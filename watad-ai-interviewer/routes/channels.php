<?php

use Illuminate\Support\Facades\Broadcast;

/*
| Candidate interview channel — authorized by the interview-bound session (the candidate has no
| account). The HR dashboard channel requires an authenticated user.
| See docs/05-api-structure.md.
*/

Broadcast::channel('interview.{publicId}', function ($user, string $publicId) {
    return session('interview_id') === $publicId;
});

Broadcast::channel('hr.dashboard', fn ($user) => $user !== null);

Broadcast::channel('hr.interview.{id}', fn ($user, string $id) => $user !== null);
