<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use Illuminate\Mail\Markdown;

/**
 * Every notification carrying an action button repeats its URL in the
 * subcopy, for somebody whose mail client will not let them click it.
 *
 * Laravel's own view writes that as `[$url]($url)`, which is right for
 * the HTML half and wrong for the text one: nothing parses markdown in a
 * text/plain body, so it arrives as literal brackets around a duplicated
 * address — the shape a badly-built phishing mail has, on what is often
 * the first message an installation ever sends anybody. Seen in the wild
 * on a real password reset before it was fixed.
 */
beforeEach(function () {
    User::factory()->create();
});

/** The two halves Laravel builds for one markdown notification. */
function renderResetMail(): array
{
    $user = User::factory()->create();
    $mail = (new ResetPasswordNotification(str_repeat('a', 64)))->toMail($user);
    $mail->viewData['actionText'] = $mail->actionText;

    $markdown = app(Markdown::class);
    $view = $mail->markdown ?: 'notifications::email';
    $data = array_merge($mail->toArray(), $mail->viewData);

    return [
        'text' => (string) $markdown->renderText($view, $data),
        'html' => (string) $markdown->render($view, $data),
        'url' => $mail->actionUrl,
    ];
}

it('spells the action URL out plainly in the text half', function () {
    ['text' => $text, 'url' => $url] = renderResetMail();

    expect($text)->toContain($url)
        ->and($text)->not->toContain('](')
        ->and($text)->not->toContain('['.$url);
});

it('still links the action URL in the html half', function () {
    ['html' => $html, 'url' => $url] = renderResetMail();

    // Twice: the button itself, and the subcopy that repeats it.
    expect(substr_count($html, 'href="'.e($url).'"'))->toBe(2)
        // The markdown must have been parsed, not passed through.
        ->and($html)->not->toContain('['.e($url).']');
});

it('does not repeat the URL more than the two places that need it', function () {
    ['text' => $text, 'url' => $url] = renderResetMail();

    // Once after the button label, once in the subcopy. A third meant the
    // markdown link had been left in place.
    expect(substr_count($text, $url))->toBe(2);
});
