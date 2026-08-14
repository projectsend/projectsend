<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * Every action the activity log can record — v2's replacement for v1's
 * ~45 numbered action types in ActionsLog. Modules add their own cases
 * (file.uploaded, group.created, …) as they land; the string values are
 * stable identifiers stored in the database.
 */
enum Action: string
{
    // Platform / lifecycle (v1 action 0: "ProjectSend has been installed")
    case SetupCompleted = 'setup.completed';
    case SettingsUpdated = 'settings.updated';

    // Identity
    case Login = 'auth.login';
    case Logout = 'auth.logout';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case AccountErased = 'account.erased';
    case AccountContentCascadeDeleted = 'account_content.cascade_deleted';
    case AccountContentReassigned = 'account_content.reassigned';
    // Deliberately their own cases rather than reusing user.updated: v1
    // wrote its LDAP events onto the codes for "approved/denied an account
    // request", so every LDAP login rendered in the log as an approval.
    case AccountConvertedToClient = 'account.converted_to_client';
    case AccountConvertedToStaff = 'account.converted_to_staff';
    case ClientSelfRegistered = 'client.self_registered';
    case LdapClientProvisioned = 'ldap.client_provisioned';
    case SocialClientProvisioned = 'social.client_provisioned';
    case SocialAccountLinked = 'social.account_linked';
    case SocialAccountUnlinked = 'social.account_unlinked';
    case ClientApproved = 'client.approved';
    case ClientDenied = 'client.denied';
    // Files
    case FileUploaded = 'file.uploaded';
    case FileUpdated = 'file.updated';
    case FileDeleted = 'file.deleted';
    case FileDownloaded = 'file.downloaded';
    case FilePreviewed = 'file.previewed';
    case FileAssigned = 'file.assigned';
    case FileUnassigned = 'file.unassigned';
    case FileVersionLinked = 'file.version_linked';
    case FileVersionUnlinked = 'file.version_unlinked';
    case ShareLinkCreated = 'share_link.created';
    case ShareLinkRevoked = 'share_link.revoked';
    case ShareLinkDownloaded = 'share_link.downloaded';
    case PublicFileDownloaded = 'public_file.downloaded';
    case FolderCreated = 'folder.created';
    case FolderRenamed = 'folder.renamed';
    case FolderMoved = 'folder.moved';
    case FolderDeleted = 'folder.deleted';
    case FolderShared = 'folder.shared';
    case FolderUnshared = 'folder.unshared';
    case FileMadePublic = 'file.made_public';
    case FileMadePrivate = 'file.made_private';
    case FolderMadePublic = 'folder.made_public';
    case FolderMadePrivate = 'folder.made_private';
    case UploadAborted = 'upload.aborted';
    case FileImported = 'file.imported';
    case OrphanFileDeleted = 'orphan_file.deleted';
    case OrphanFileAutoDeleted = 'orphan_file.auto_deleted';
    case ExpiredFileDeleted = 'file.expired_deleted';

    case GroupMembershipLeft = 'group.membership_left';
    case GroupMembershipRequested = 'group.membership_requested';
    case GroupMembershipApproved = 'group.membership_approved';
    case GroupMembershipDenied = 'group.membership_denied';
    case GroupCreated = 'group.created';
    case GroupUpdated = 'group.updated';
    case GroupDeleted = 'group.deleted';
    case GroupMadePublic = 'group.made_public';
    case GroupMadePrivate = 'group.made_private';
    case GroupMemberAdded = 'group.member_added';
    case GroupMemberRemoved = 'group.member_removed';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case CategoryCreated = 'category.created';
    case CategoryRenamed = 'category.renamed';
    case CategoryDeleted = 'category.deleted';
    case ClientCustomFieldCreated = 'client_custom_field.created';
    case ClientCustomFieldUpdated = 'client_custom_field.updated';
    case ClientCustomFieldDeleted = 'client_custom_field.deleted';
    case ProfileUpdated = 'profile.updated';
    case PasswordUpdated = 'password.updated';
    case TwoFactorEnabled = 'two_factor.enabled';
    case TwoFactorDisabled = 'two_factor.disabled';
    case TwoFactorRecoveryCodesRegenerated = 'two_factor.recovery_codes_regenerated';
    // Distinct from TwoFactorDisabled: that is somebody turning off their
    // own second factor, this is an administrator turning off somebody
    // else's. Same end state, very different thing to find in an audit
    // trail — one of them is the shape an account takeover would take.
    case TwoFactorReset = 'two_factor.reset';

    // API tokens. Minting one creates a long-lived credential that acts
    // with the owner's permissions outside any browser session, so both
    // ends of its life are audit events in their own right.
    case ApiTokenCreated = 'api_token.created';
    case ApiTokenUpdated = 'api_token.updated';
    case ApiTokenRevoked = 'api_token.revoked';

    // Custom assets (community-modules package — see
    // App\Modules\Audit\Listeners\LogCustomAssetActivity, which
    // translates that package's events into these cases; the package
    // itself has no dependency on this enum or on ActivityLogger).
    case CustomAssetCreated = 'custom_asset.created';
    case CustomAssetUpdated = 'custom_asset.updated';
    case CustomAssetDeleted = 'custom_asset.deleted';
    case CustomAssetEnabled = 'custom_asset.enabled';
    case CustomAssetDisabled = 'custom_asset.disabled';

    // Comments. The body is deliberately never recorded in the log's
    // context: a comment can be visible to one client only, and the
    // activity log is read by staff whose file scope may not include
    // that client's thread.
    case CommentPosted = 'comment.posted';
    // A visitor's comment has no account behind it, so it would otherwise
    // log with a null actor and read as "System" — the audit trail saying
    // the installation commented on its own file, with the one detail
    // moderation cares about (who claimed to write it) thrown away. Same
    // reason FileDownloaded, PublicFileDownloaded and ShareLinkDownloaded
    // are three cases rather than one: same event, different origin.
    case CommentPostedByVisitor = 'comment.posted_by_visitor';
    case CommentEdited = 'comment.edited';
    case CommentDeleted = 'comment.deleted';
    case CommentApproved = 'comment.approved';

    /**
     * Full sentence for a log row, with :placeholders resolved from the
     * entry's subject name (:subject) and context keys. English text is
     * the translation key; translations keep the placeholders.
     */
    public function template(): string
    {
        return match ($this) {
            self::SetupCompleted => 'Installed ProjectSend',
            self::SettingsUpdated => 'Updated the system settings (:section)',
            self::Login => 'Logged in',
            self::Logout => 'Logged out',
            self::UserCreated => 'Created the account ":subject"',
            self::UserUpdated => 'Updated the account ":subject"',
            self::UserDeleted => 'Deleted the account ":name"',
            self::AccountConvertedToClient => 'Converted the staff account ":subject" to a client',
            self::AccountConvertedToStaff => 'Converted the client account ":subject" to staff (:role)',
            self::LdapClientProvisioned => 'Created a client account from the directory on first sign-in',
            self::SocialClientProvisioned => 'Created a client account from :provider on first sign-in',
            self::SocialAccountLinked => 'Connected the :provider account of ":subject"',
            self::SocialAccountUnlinked => 'Disconnected the :provider account of ":subject"',
            self::UserActivated => 'Activated the account ":subject"',
            self::UserDeactivated => 'Deactivated the account ":subject"',
            self::AccountErased => 'Permanently erased a deleted account',
            self::AccountContentCascadeDeleted => 'Deleted :files file(s) and :folders folder(s) belonging to the deleted account ":name"',
            self::AccountContentReassigned => 'Reassigned :files file(s) and :folders folder(s) from the deleted account ":name" to :target',
            self::ClientSelfRegistered => 'Registered a new client account',
            self::ClientApproved => 'Approved the account request of ":subject"',
            self::ClientDenied => 'Denied the account request of ":name"',
            self::FileUploaded => 'Uploaded the file ":subject"',
            self::FileUpdated => 'Updated the file ":subject"',
            self::FileDeleted => 'Deleted the file ":name"',
            self::FileDownloaded => 'Downloaded the file ":subject"',
            self::FilePreviewed => 'Previewed the file ":subject"',
            self::FileAssigned => 'Assigned the file ":subject" to :target',
            self::FileUnassigned => 'Removed the file ":subject" from :target',
            // :previous is a name snapshot in context, not a lookup — same
            // reason FileDeleted carries :name: the entry has to still read
            // correctly once the original is gone.
            self::FileVersionLinked => 'Marked the file ":subject" as a revision of ":previous"',
            self::FileVersionUnlinked => 'Removed the version link from the file ":subject"',
            self::ShareLinkCreated => 'Created a public link for the file ":subject"',
            self::ShareLinkRevoked => 'Revoked a public link for the file ":subject"',
            self::ShareLinkDownloaded => 'Downloaded the file ":subject" via a public link',
            self::PublicFileDownloaded => 'Downloaded the file ":subject" via the public group listing',
            self::FolderCreated => 'Created the folder ":subject"',
            self::FolderRenamed => 'Renamed the folder ":subject"',
            self::FolderMoved => 'Moved the folder ":subject"',
            self::FolderDeleted => 'Deleted the folder ":name" and its contents',
            self::FolderShared => 'Shared the folder ":subject" with :target',
            self::FolderUnshared => 'Stopped sharing the folder ":subject" with :target',
            self::FileMadePublic => 'Made the file ":subject" public',
            self::FileMadePrivate => 'Made the file ":subject" private',
            self::FolderMadePublic => 'Made the folder ":subject" public',
            self::FolderMadePrivate => 'Made the folder ":subject" private',
            self::UploadAborted => 'Cancelled uploading the file ":name"',
            self::CommentPosted => 'Commented on the file ":subject"',
            self::CommentPostedByVisitor => ':guest commented on the file ":subject"',
            self::CommentEdited => 'Edited a comment on the file ":subject"',
            self::CommentDeleted => 'Deleted a comment on the file ":subject"',
            self::CommentApproved => 'Approved a comment on the file ":subject"',
            self::FileImported => 'Imported the orphan file ":subject"',
            self::OrphanFileDeleted => 'Deleted the orphan file ":name"',
            self::OrphanFileAutoDeleted => 'Deleted the orphan file ":name"',
            self::ExpiredFileDeleted => 'Deleted the expired file ":name"',
            self::GroupMembershipLeft => 'Left the group ":subject"',
            self::GroupMembershipRequested => 'Requested membership to the group ":subject"',
            self::GroupMembershipApproved => 'Approved the membership of ":member" to the group ":subject"',
            self::GroupMembershipDenied => 'Denied the membership of ":member" to the group ":subject"',
            self::GroupCreated => 'Created the group ":subject"',
            self::GroupUpdated => 'Updated the group ":subject"',
            self::GroupDeleted => 'Deleted the group ":name"',
            self::GroupMadePublic => 'Made the group ":subject" public',
            self::GroupMadePrivate => 'Made the group ":subject" private',
            self::GroupMemberAdded => 'Added ":member" to the group ":subject"',
            self::GroupMemberRemoved => 'Removed ":member" from the group ":subject"',
            self::RoleCreated => 'Created the role ":subject"',
            self::RoleUpdated => 'Updated the role ":subject"',
            self::RoleDeleted => 'Deleted the role ":name"',
            self::CategoryCreated => 'Created the category ":subject"',
            self::CategoryRenamed => 'Renamed the category to ":subject"',
            self::CategoryDeleted => 'Deleted the category ":name"',
            self::ClientCustomFieldCreated => 'Created the custom field ":name"',
            self::ClientCustomFieldUpdated => 'Updated the custom field ":name"',
            self::ClientCustomFieldDeleted => 'Deleted the custom field ":name"',
            self::ProfileUpdated => 'Updated their profile information',
            self::PasswordUpdated => 'Changed their password',
            self::TwoFactorEnabled => 'Enabled two-factor authentication',
            self::TwoFactorDisabled => 'Disabled two-factor authentication',
            self::TwoFactorRecoveryCodesRegenerated => 'Regenerated two-factor recovery codes',
            self::TwoFactorReset => 'Removed two-factor authentication from ":subject"',
            self::ApiTokenCreated => 'Created the API token ":token_name"',
            self::ApiTokenUpdated => 'Updated the API token ":token_name"',
            self::ApiTokenRevoked => 'Revoked the API token ":token_name"',
            self::CustomAssetCreated => 'Created the custom asset ":subject"',
            self::CustomAssetUpdated => 'Updated the custom asset ":subject"',
            self::CustomAssetDeleted => 'Deleted the custom asset ":name"',
            self::CustomAssetEnabled => 'Enabled the custom asset ":subject"',
            self::CustomAssetDisabled => 'Disabled the custom asset ":subject"',
        };
    }

    /**
     * Generic description for filter dropdowns (also the translation key).
     */
    public function description(): string
    {
        return match ($this) {
            self::SetupCompleted => 'ProjectSend was installed',
            self::SettingsUpdated => 'System settings were updated',
            self::Login => 'Logged in',
            self::Logout => 'Logged out',
            self::UserCreated => 'An account was created',
            self::UserUpdated => 'An account was updated',
            self::UserDeleted => 'An account was deleted',
            self::AccountConvertedToClient => 'A staff account was converted to a client',
            self::AccountConvertedToStaff => 'A client account was converted to staff',
            self::LdapClientProvisioned => 'A client account was created from the directory',
            self::SocialClientProvisioned => 'A client account was created from :provider',
            self::SocialAccountLinked => 'A :provider account was connected',
            self::SocialAccountUnlinked => 'A :provider account was disconnected',
            self::UserActivated => 'An account was activated',
            self::UserDeactivated => 'An account was deactivated',
            self::AccountErased => 'An account was permanently erased',
            self::AccountContentCascadeDeleted => 'A deleted account\'s files and folders were deleted',
            self::AccountContentReassigned => 'A deleted account\'s files and folders were reassigned',
            self::ClientSelfRegistered => 'A client registered an account',
            self::ClientApproved => 'A client account request was approved',
            self::ClientDenied => 'A client account request was denied',
            self::FileUploaded => 'A file was uploaded',
            self::FileUpdated => 'A file was updated',
            self::FileDeleted => 'A file was deleted',
            self::FileDownloaded => 'A file was downloaded',
            self::FilePreviewed => 'A file was previewed',
            self::FileAssigned => 'A file was assigned',
            self::FileUnassigned => 'A file assignment was removed',
            self::FileVersionLinked => 'A file was marked as a revision of another',
            self::FileVersionUnlinked => 'A file version link was removed',
            self::ShareLinkCreated => 'A public link was created',
            self::ShareLinkRevoked => 'A public link was revoked',
            self::ShareLinkDownloaded => 'A file was downloaded via a public link',
            self::PublicFileDownloaded => 'A file was downloaded via the public group listing',
            self::FolderCreated => 'A folder was created',
            self::FolderRenamed => 'A folder was renamed',
            self::FolderMoved => 'A folder was moved',
            self::FolderDeleted => 'A folder was deleted',
            self::FolderShared => 'A folder was shared',
            self::FolderUnshared => 'A folder was unshared',
            self::FileMadePublic => 'A file was made public',
            self::FileMadePrivate => 'A file was made private',
            self::FolderMadePublic => 'A folder was made public',
            self::FolderMadePrivate => 'A folder was made private',
            self::UploadAborted => 'An upload was cancelled',
            self::CommentPosted => 'A comment was posted on a file',
            self::CommentPostedByVisitor => 'A visitor commented on a file',
            self::CommentEdited => 'A comment was edited',
            self::CommentDeleted => 'A comment was deleted',
            self::CommentApproved => 'A comment was approved',
            self::FileImported => 'An orphan file was imported',
            self::OrphanFileDeleted => 'An orphan file was deleted',
            self::OrphanFileAutoDeleted => 'An orphan file was automatically deleted after its retention grace period passed',
            self::ExpiredFileDeleted => 'An expired file was automatically deleted after its retention grace period passed',
            self::GroupMembershipLeft => 'A client left a group',
            self::GroupMembershipRequested => 'A group membership was requested',
            self::GroupMembershipApproved => 'A group membership request was approved',
            self::GroupMembershipDenied => 'A group membership request was denied',
            self::GroupCreated => 'A group was created',
            self::GroupUpdated => 'A group was updated',
            self::GroupDeleted => 'A group was deleted',
            self::GroupMadePublic => 'A group was made public',
            self::GroupMadePrivate => 'A group was made private',
            self::GroupMemberAdded => 'A client was added to a group',
            self::GroupMemberRemoved => 'A client was removed from a group',
            self::RoleCreated => 'A role was created',
            self::RoleUpdated => 'A role was updated',
            self::RoleDeleted => 'A role was deleted',
            self::CategoryCreated => 'A category was created',
            self::CategoryRenamed => 'A category was renamed',
            self::CategoryDeleted => 'A category was deleted',
            self::ClientCustomFieldCreated => 'A custom field was created',
            self::ClientCustomFieldUpdated => 'A custom field was updated',
            self::ClientCustomFieldDeleted => 'A custom field was deleted',
            self::ProfileUpdated => 'Profile information was updated',
            self::PasswordUpdated => 'The account password was changed',
            self::TwoFactorEnabled => 'Two-factor authentication was enabled',
            self::TwoFactorDisabled => 'Two-factor authentication was disabled',
            self::TwoFactorRecoveryCodesRegenerated => 'Two-factor recovery codes were regenerated',
            self::TwoFactorReset => 'Two-factor authentication was removed by an administrator',
            self::ApiTokenCreated => 'An API token was created',
            self::ApiTokenUpdated => 'An API token was updated',
            self::ApiTokenRevoked => 'An API token was revoked',
            self::CustomAssetCreated => 'A custom asset was created',
            self::CustomAssetUpdated => 'A custom asset was updated',
            self::CustomAssetDeleted => 'A custom asset was deleted',
            self::CustomAssetEnabled => 'A custom asset was enabled',
            self::CustomAssetDisabled => 'A custom asset was disabled',
        };
    }
}
