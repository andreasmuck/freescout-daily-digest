<?php

namespace Modules\DailyDigest\Http\Controllers;

use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\DailyDigest\Services\Settings;
use Modules\DailyDigest\Services\DigestBuilder;
use Modules\DailyDigest\Services\RecipientLocale;

class DigestController extends Controller
{
    public function preview(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);
        $this->validateUser($request);
        $user = User::where('status', User::STATUS_ACTIVE)->where('type', User::TYPE_USER)->findOrFail((int) $request->input('user'));
        $digest = app(DigestBuilder::class)->build($user, Settings::all(), Carbon::now('UTC'));
        $emailHtml = RecipientLocale::run($user, function () use ($digest) {
            return view('dailydigest::email', $digest)->render();
        });
        return response()->view('dailydigest::preview', ['digest' => $digest, 'emailHtml' => $emailHtml])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function validateUser(Request $request)
    {
        abort_unless(ctype_digit((string) $request->input('user')) && (int) $request->input('user') > 0, 422);
    }
}
