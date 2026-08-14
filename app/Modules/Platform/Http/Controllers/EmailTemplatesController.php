<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\Notifications\AdminClientRegisteredNotification;
use App\Modules\Clients\Notifications\ClientAccountApprovedNotification;
use App\Modules\Clients\Notifications\ClientAccountDeniedNotification;
use App\Modules\Clients\Notifications\ClientAccountEditedNotification;
use App\Modules\Clients\Notifications\ClientWelcomeNotification;
use App\Modules\Comments\Notifications\CommentDigestNotification;
use App\Modules\Comments\Notifications\CommentPostedNotification;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Notifications\FileShareDigestNotification;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Groups\Notifications\GroupMembershipApprovedNotification;
use App\Modules\Groups\Notifications\GroupMembershipDeniedNotification;
use App\Modules\Groups\Notifications\GroupMembershipRequestedNotification;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Notifications\TwoFactorResetNotification;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\EmailTemplate;
use App\Modules\Platform\Notifications\EmailTemplateResolver;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use App\Modules\Platform\Theming\EmailPreviewRenderer;
use App\Modules\Platform\Theming\EmailThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Notifications\Messages\MailMessage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an admin reword any transactional email (subject + body) without
 * touching code — v1 parity (edit_email_templates). Only the wording is
 * editable; action buttons/links stay code-controlled on every slot —
 * see EmailTemplateSlot and RendersOverridableMail.
 */
class EmailTemplatesController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly EmailTemplateResolver $resolver,
        private readonly EmailPreviewRenderer $preview,
        private readonly EmailThemeService $emailTheme,
    ) {}

    public function index(): Response
    {
        return Inertia::render('system/settings/email-templates/index', [
            'templates' => array_map(fn (EmailTemplateSlot $slot): array => [
                'slot' => $slot->value,
                'label' => $slot->label(),
                'customized' => $this->resolver->resolve($slot) !== null,
            ], EmailTemplateSlot::cases()),
        ]);
    }

    public function edit(EmailTemplateSlot $slot): Response
    {
        $override = $this->resolver->resolve($slot);

        return Inertia::render('system/settings/email-templates/edit', [
            'slot' => $slot->value,
            'label' => $slot->label(),
            'subject' => $override['subject'] ?? $slot->defaultSubject(),
            'body' => $override['body'] ?? $slot->defaultBody(),
            'customized' => $override !== null,
            'placeholders' => $slot->placeholders(),
        ]);
    }

    public function update(Request $request, EmailTemplateSlot $slot): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        EmailTemplate::query()->updateOrCreate(['slot' => $slot->value], $validated);

        $this->resolver->flush();
        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'email-template', 'slot' => $slot->value]);

        return back()->with('success', __('Email template saved.'));
    }

    public function destroy(EmailTemplateSlot $slot): RedirectResponse
    {
        EmailTemplate::query()->where('slot', $slot->value)->delete();

        $this->resolver->flush();
        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'email-template-reset', 'slot' => $slot->value]);

        return back()->with('success', __('Email template reset to default.'));
    }

    /**
     * Renders this slot's *effective* email (the saved override if one
     * exists, otherwise the code default — toMail() resolves that itself
     * the same way a real send would) with representative sample content,
     * through the currently active email theme. Never sends anything.
     */
    public function preview(EmailTemplateSlot $slot): HttpResponse
    {
        $notifiable = new User(['name' => 'Jane Client', 'email' => 'preview@example.com']);
        $mail = $this->sampleMail($slot, $notifiable);

        return new HttpResponse(
            $this->preview->render($mail, $this->emailTheme->currentThemeKey()),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private function sampleMail(EmailTemplateSlot $slot, User $notifiable): MailMessage
    {
        return match ($slot) {
            EmailTemplateSlot::FileShared => (new FileSharedNotification('sample-file.pdf', isFolder: false))->toMail($notifiable),
            EmailTemplateSlot::FolderShared => (new FileSharedNotification('Sample Folder', isFolder: true))->toMail($notifiable),
            EmailTemplateSlot::FileShareDigest => (new FileShareDigestNotification([
                new PendingNotification(['subject_name' => 'sample-file.pdf', 'context' => ['is_folder' => false]]),
                new PendingNotification(['subject_name' => 'Sample Folder', 'context' => ['is_folder' => true]]),
            ]))->toMail($notifiable),
            EmailTemplateSlot::CommentPosted => (new CommentPostedNotification('sample-file.pdf', 'Jane Client', null))->toMail($notifiable),
            EmailTemplateSlot::CommentDigest => (new CommentDigestNotification([
                new PendingNotification(['subject_name' => 'sample-file.pdf', 'context' => ['author_name' => 'Jane Client']]),
                new PendingNotification(['subject_name' => 'Brand Guidelines.pdf', 'context' => ['author_name' => 'Ada Admin']]),
            ]))->toMail($notifiable),
            EmailTemplateSlot::ClientAccountApproved => (new ClientAccountApprovedNotification)->toMail($notifiable),
            EmailTemplateSlot::ClientAccountDenied => (new ClientAccountDeniedNotification('Jane Client'))->toMail($notifiable),
            EmailTemplateSlot::ClientWelcome => (new ClientWelcomeNotification)->toMail($notifiable),
            EmailTemplateSlot::ClientAccountEdited => (new ClientAccountEditedNotification)->toMail($notifiable),
            EmailTemplateSlot::AdminClientRegistered => (new AdminClientRegisteredNotification('Jane Client', 'preview@example.com', pendingApproval: false))->toMail($notifiable),
            EmailTemplateSlot::AdminClientUploaded => (new AdminClientUploadedNotification('Jane Client', 'sample-file.pdf', 0))->toMail($notifiable),
            EmailTemplateSlot::GroupMembershipRequested => (new GroupMembershipRequestedNotification('Jane Client', 'Sample Group'))->toMail($notifiable),
            EmailTemplateSlot::GroupMembershipApproved => (new GroupMembershipApprovedNotification('Sample Group'))->toMail($notifiable),
            EmailTemplateSlot::GroupMembershipDenied => (new GroupMembershipDeniedNotification('Sample Group'))->toMail($notifiable),
            EmailTemplateSlot::PasswordReset => (new ResetPasswordNotification('sample-token'))->toMail($notifiable),
            EmailTemplateSlot::TwoFactorReset => (new TwoFactorResetNotification)->toMail($notifiable),
        };
    }
}
