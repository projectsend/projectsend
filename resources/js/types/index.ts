import { type InstallKind } from '@/components/update-instructions';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url?: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
    badge?: number;
}

export type Edition = 'community' | 'cloud';

export type Capability =
    | 'users.manage'
    | 'storage.configure'
    | 'email.transport.configure'
    | 'system.updates'
    | 'scheduler.monitoring'
    | 'custom_assets.manage'
    | 'branding.customize'
    | 'captcha.managed_keys';

export interface SocialLoginProvider {
    provider: string;
    label: string;
}

export type CaptchaProvider = 'turnstile' | 'recaptcha_v2' | 'recaptcha_v3';

/** The forms a token can be minted for — also the provider's action name. */
export type CaptchaAction = 'login' | 'register' | 'password_reset' | 'comment';

export interface CaptchaConfig {
    provider: CaptchaProvider;
    /** Public by design. The secret never reaches the browser. */
    site_key: string;
    /** Which forms will ask for a token on this installation. */
    forms: CaptchaAction[];
}

/**
 * What this installation will accept as a new password. Length and a
 * breach check only — there are deliberately no composition rules, so
 * there is nothing else for a form to display.
 */
export interface PasswordPolicy {
    min_length: number;
    reject_breached: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    edition: Edition;
    version: string;
    links: {
        /** projectsend.org, or projectsend.cloud on a managed installation. */
        website: string;
        source: string;
        discord: string;
        /** Absent on a managed installation — those customers already pay. */
        open_collective?: string;
    };
    /**
     * Whether this installation names ProjectSend to its clients and
     * visitors. Always true unless the (cloud-only) Branding module
     * says otherwise — see the ResolvingAttribution hook. Read it
     * through `<PoweredBy>` rather than directly; staff surfaces
     * deliberately ignore it.
     */
    attribution: boolean;
    capabilities: Capability[];
    /** Identity providers that are switched on and fully configured. */
    social_login: SocialLoginProvider[];
    /** The CAPTCHA in force, or null when this installation has none. */
    captcha: CaptchaConfig | null;
    /** Rules every form that sets a password should state up front. */
    password_policy: PasswordPolicy;
    pending: {
        account_requests?: number;
        membership_requests?: number;
        /** Comments held for approval anywhere in this viewer's library. */
        comments?: number;
        notifications_unread?: number;
    };
    update_notice: {
        version: string;
        title: string;
        notes: string;
        url: string;
        published_at: string;
        /** Decides which upgrade instructions the dialog prints. */
        install_kind: InstallKind;
    } | null;
    /**
     * Set when the code this process is running is not the code the
     * installation was last updated to — see RunningCodeState. Almost
     * always a PHP-FPM that was never reloaded after an update, which is
     * otherwise completely silent.
     */
    code_notice: {
        reason: 'stale_code' | 'pending_update';
        /** What the last update applied. */
        applied: string;
        /** What this process compiled. */
        running: string;
        applied_at: string;
        install_kind: InstallKind;
    } | null;
    /**
     * Set when zip builds have been queued and no worker ever picked them
     * up — see StalledZipBuilds. Almost always a manual install whose
     * worker command predates the `zips` queue, which is silent in the
     * same way code_notice above is: everything else keeps working.
     */
    worker_notice: {
        /** When the oldest unstarted build was asked for. */
        waiting_since: string;
    } | null;
    locale: string;
    /**
     * The IANA zone this viewer reads dates in — their own preference, or
     * the installation's setting for anyone who has not chosen (including
     * anonymous visitors). Read it through `useFormatDate()` rather than
     * directly.
     */
    timezone: string;
    /**
     * False while `timezone` above is only a fallback, which is the signal
     * TimezoneDetector waits for — see timezone-detector.tsx.
     */
    timezone_is_explicit: boolean;
    locales: string[];
    /**
     * Installed catalogues an administrator has not switched on. Always 0
     * for anyone who cannot manage them — see HandleInertiaRequests.
     */
    locales_disabled: number;
    translations: Record<string, string>;
    flash: {
        success: string | null;
        error: string | null;
    };
    // Shared by the (cloud-only) projectsend/cloud-modules Branding
    // module's own ServiceProvider, not by this app's own
    // HandleInertiaRequests — absent entirely on community installs.
    branding?: { logo_url: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    type: 'staff' | 'client';
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
