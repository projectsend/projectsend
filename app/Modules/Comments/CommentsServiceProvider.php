<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Modules\Comments\Models\FileComment;
use App\Modules\Comments\Notifications\CommentDigestNotification;
use App\Modules\Comments\Notifications\CommentPostedNotification;
use App\Modules\Notifications\NotificationTypeDefinition;
use App\Modules\Notifications\NotificationTypeRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Comments are their own module rather than part of Files: the visibility
 * model here is a distinct concern from file access (it consumes it, it
 * does not extend it), and the same machinery is the obvious home for
 * comments on folders or groups later, which would sit awkwardly inside
 * the Files namespace.
 */
class CommentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(FileComment::class, FileCommentPolicy::class);

        // Emailed through the digest, not per comment: answering several
        // in a row is exactly how staff work, and one email each would
        // flood anyone watching a few clients. Defaulted on so a reply
        // reaches somebody who rarely signs in — which is the whole reason
        // to email at all — and switchable off per person for anyone who
        // finds it noisy.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'file_comment.posted',
            label: 'Someone commented on a file',
            template: ':authorName commented on ":fileName"',
            defaultEmailEnabled: true,
            digestMail: CommentPostedNotification::class,
            digestMailMany: CommentDigestNotification::class,
            url: fn (array $data): string => route('comments.go', $data['fileId']),
        ));

        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'file_comment.pending',
            label: 'A comment is waiting for approval',
            template: ':authorName commented on ":fileName" and is waiting for approval',
            // Straight to the held ones. The screen lists every comment, so
            // an unfiltered link would land the moderator in a haystack
            // containing the thing they were told about.
            url: fn (array $data): string => route('comments.index', ['status' => 'pending']),
        ));
    }
}
