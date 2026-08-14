<?php

declare(strict_types=1);

namespace App\Modules\Platform\Notifications;

/**
 * Every transactional email an admin may customize the wording of.
 * `TestEmailNotification` is deliberately excluded — it's a diagnostic
 * tool, not a real user-facing notice.
 *
 * `defaultSubject()`/`defaultBody()` are plain English strings used only
 * to prefill the admin edit form and show "what it currently says" —
 * the real send path keeps its own `__()` calls untouched for the
 * non-customized case (still translated per recipient locale). The
 * action button (login link, reset link, "view file" link, …) is never
 * part of the customizable body — it stays fixed in code on every slot.
 * FileShareDigest's item list is the same kind of fixed addition: the
 * customizable body is just the intro line, and every shared item's
 * name is always appended after it as its own line — see
 * FileShareDigestNotification.
 */
enum EmailTemplateSlot: string
{
    case FileShared = 'file_shared';
    case FolderShared = 'folder_shared';
    case FileShareDigest = 'file_share_digest';
    case ClientAccountApproved = 'client_account_approved';
    case ClientAccountDenied = 'client_account_denied';
    case ClientWelcome = 'client_welcome';
    case ClientAccountEdited = 'client_account_edited';
    case AdminClientRegistered = 'admin_client_registered';
    case AdminClientUploaded = 'admin_client_uploaded';
    case GroupMembershipRequested = 'group_membership_requested';
    case GroupMembershipApproved = 'group_membership_approved';
    case GroupMembershipDenied = 'group_membership_denied';
    case CommentPosted = 'comment_posted';
    case CommentDigest = 'comment_digest';
    case PasswordReset = 'password_reset';
    case TwoFactorReset = 'two_factor_reset';

    public function label(): string
    {
        return match ($this) {
            self::FileShared => 'File shared with a client',
            self::FolderShared => 'Folder shared with a client',
            self::FileShareDigest => 'Multiple files/folders shared at once (digest)',
            self::ClientAccountApproved => 'Client account approved',
            self::ClientAccountDenied => 'Client account denied',
            self::ClientWelcome => 'Client welcome (staff-created account)',
            self::ClientAccountEdited => 'Client account edited',
            self::AdminClientRegistered => 'Admin: new client registered',
            self::AdminClientUploaded => 'Admin: client uploaded a file',
            self::GroupMembershipRequested => 'Admin: group membership requested',
            self::GroupMembershipApproved => 'Group membership approved',
            self::GroupMembershipDenied => 'Group membership denied',
            self::CommentPosted => 'New comment on a file',
            self::CommentDigest => 'Multiple new comments (digest)',
            self::PasswordReset => 'Password reset',
            self::TwoFactorReset => 'Two-factor authentication removed by an admin',
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::FileShared => 'A file has been shared with you',
            self::FolderShared => 'A folder has been shared with you',
            self::FileShareDigest => ':count items have been shared with you',
            self::CommentPosted => 'New comment on ":name"',
            self::CommentDigest => ':count new comments',
            self::ClientAccountApproved => 'Your account has been approved',
            self::ClientAccountDenied => 'Your account request was denied',
            self::ClientWelcome => 'Welcome',
            self::ClientAccountEdited => 'Your account was updated',
            self::AdminClientRegistered => 'A new client has registered',
            self::AdminClientUploaded => 'A client uploaded a file',
            self::GroupMembershipRequested => 'A client requested to join a group',
            self::GroupMembershipApproved => 'Your group membership request was approved',
            self::GroupMembershipDenied => 'Your group membership request was denied',
            self::PasswordReset => 'Reset Password Notification',
            self::TwoFactorReset => 'Two-factor authentication was removed from your account',
        };
    }

    public function defaultBody(): string
    {
        return match ($this) {
            self::FileShared => 'The file ":name" has been shared with you.',
            self::FolderShared => 'The folder ":name" has been shared with you.',
            self::FileShareDigest => 'The following :count items have been shared with you:',
            self::CommentPosted => ':author commented on ":name".',
            self::CommentDigest => 'There are :count new comments for you:',
            self::ClientAccountApproved => 'Your account request has been approved. You can now log in.',
            self::ClientAccountDenied => "Hello :name,\n\nYour account request has been denied.",
            self::ClientWelcome => 'An account has been created for you. You can log in now.',
            self::ClientAccountEdited => 'Your account details were recently changed by an administrator. Contact your administrator if this was not expected.',
            self::AdminClientRegistered => 'A new client account was created: :name (:email).',
            self::AdminClientUploaded => 'The file ":file" was uploaded by :client.',
            self::GroupMembershipRequested => 'New membership request from :client for the group ":group".',
            self::GroupMembershipApproved => 'Your request to join the group ":group" has been approved.',
            self::GroupMembershipDenied => 'Your request to join the group ":group" has been denied.',
            self::PasswordReset => "You are receiving this email because we received a password reset request for your account.\n\nThis password reset link will expire in :count minutes.\n\nIf you did not request a password reset, no further action is required.",
            self::TwoFactorReset => 'An administrator removed two-factor authentication from your account. You can set it up again from your security settings. Contact your administrator if this was not expected.',
        };
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        return match ($this) {
            self::FileShared, self::FolderShared => ['name' => 'The file or folder name'],
            self::FileShareDigest => ['count' => 'How many files/folders were shared at once'],
            self::CommentPosted => ['name' => 'The file name', 'author' => "The commenter's name"],
            // The list of files is appended after the body, like the share
            // digest's — never part of the customizable text.
            self::CommentDigest => ['count' => 'How many new comments there are'],
            self::ClientAccountApproved, self::ClientWelcome, self::ClientAccountEdited => [],
            self::ClientAccountDenied => ['name' => "The client's name"],
            self::AdminClientRegistered => ['name' => "The client's name", 'email' => "The client's email address"],
            self::AdminClientUploaded => ['file' => 'The file name', 'client' => "The uploading client's name"],
            self::GroupMembershipRequested => ['client' => "The client's name", 'group' => 'The group name'],
            self::GroupMembershipApproved, self::GroupMembershipDenied => ['group' => 'The group name'],
            self::PasswordReset => ['count' => 'Minutes until the reset link expires'],
            self::TwoFactorReset => [],
        };
    }
}
