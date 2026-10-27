<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Clients\Notifications\ClientInvitationNotification;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Platform\Notifications\EmailTemplate;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * A notification's lines are rendered as Markdown, and most of them carry a
 * value somebody else chose: a client's display name, a file name, a
 * comment author. Blade escapes HTML in those, not Markdown, so a client
 * named `[Re-verify your account](https://…)` put a working link in mail
 * the installation sent its own staff (GHSA-64r6-jqg3-7x36).
 */
beforeEach(function () {
    User::factory()->create();
});

const PLANTED_LINK = '[Re-verify your account](https://attacker.example/login)';
const PLANTED_IMAGE = '![](https://attacker.example/p.gif)';

/** @return array{html: string, text: string} */
function renderBothHalves(MailMessage $mail): array
{
    $markdown = app(Markdown::class);
    $view = $mail->markdown ?: 'notifications::email';
    $data = array_merge($mail->toArray(), $mail->viewData);

    return [
        'html' => (string) $markdown->render($view, $data),
        'text' => (string) $markdown->renderText($view, $data),
    ];
}

test('a display name cannot plant a link or an image in a line', function () {
    $mail = (new AdminClientUploadedNotification(PLANTED_LINK, PLANTED_IMAGE, 1))->toMail(User::first());

    $html = renderBothHalves($mail)['html'];

    expect($html)
        ->not->toContain('href="https://attacker.example')
        ->not->toContain('src="https://attacker.example')
        ->toContain('[Re-verify your account](https://attacker.example/login)');
});

test('a name cannot plant a link in the greeting', function () {
    $mail = (new ClientInvitationNotification(PLANTED_LINK, str_repeat('a', 40)))->toMail(User::first());

    expect(renderBothHalves($mail)['html'])
        ->not->toContain('href="https://attacker.example')
        ->toContain('Hello [Re-verify your account](https://attacker.example/login)');
});

test('a value filled into a customised template cannot plant a link', function () {
    EmailTemplate::query()->create([
        'slot' => EmailTemplateSlot::AdminClientUploaded->value,
        'subject' => 'Upload',
        'body' => ':client uploaded :file.',
    ]);

    $mail = (new AdminClientUploadedNotification(PLANTED_LINK, 'scope.pdf', 1))->toMail(User::first());

    expect(renderBothHalves($mail)['html'])->not->toContain('href="https://attacker.example');
});

test('the text half shows the value exactly as it was written', function () {
    // Nothing parses Markdown in text/plain, so an escape meant for the
    // HTML half would arrive there as a stray backslash: my\_report\.pdf.
    $mail = (new AdminClientUploadedNotification('Ann_Lee', 'my_report (v2).pdf', 1))->toMail(User::first());

    expect(renderBothHalves($mail)['text'])
        ->toContain('my_report (v2).pdf')
        ->toContain('Ann_Lee')
        ->not->toContain('\\');
});

test('an ordinary file name still reads normally in the HTML half', function () {
    $mail = (new AdminClientUploadedNotification('Ann_Lee', 'my_report (v2).pdf', 1))->toMail(User::first());

    expect(renderBothHalves($mail)['html'])
        ->toContain('my_report (v2).pdf')
        ->toContain('Ann_Lee')
        ->not->toContain('\\');
});

test('a line the notification marks as its own Markdown keeps its link', function () {
    // The escape hatch: a link the code builds itself, passed as an
    // HtmlString. Never wrap a line that carries a value from outside.
    $mail = (new MailMessage)->line(new HtmlString('[See it](https://example.test/my-files)'));

    expect(renderBothHalves($mail)['html'])->toContain('href="https://example.test/my-files"');
});
