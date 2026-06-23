<?php

declare(strict_types=1);

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use Illuminate\View\View;

class InterviewRoomController extends Controller
{
    public function show(Interview $interview): View
    {
        abort_unless(session('interview_id') === $interview->public_id, 403);

        $interview->load('avatar', 'jobPosition');

        return view('candidate.room', ['interview' => $interview]);
    }
}
